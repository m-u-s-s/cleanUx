<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES DEUX CLES QUE L'ECRAN NATIF LIT POUR AFFICHER UN PRIX.
 *
 * Le type `Booking` de l'application declarait `total_price`. La charge utile n'a jamais porte
 * ce nom : elle envoie `estimated_price`. Le champ valait donc toujours `undefined`, et les
 * trois endroits qui le testaient avant d'afficher ne s'affichaient jamais — dont le bouton
 * « Payer » de l'ecran de detail. Cinq fixtures ecrites a la main figeaient la fausse clef,
 * ce qui rendait la suite native verte pendant que l'ecran restait muet.
 *
 * Ce test vit ici, cote serveur, parce que c'est le seul endroit ou la charge utile ne peut
 * pas etre inventee : un fichier de test natif peut affirmer n'importe quelle forme.
 */
class PrixDeLaReservationLuParLEcranTest extends TestCase
{
    use RefreshDatabase;

    private function clientAvecReservation(float $prix, string $devise): User
    {
        $client = User::factory()->client()->create();

        Booking::factory()->create([
            'client_id' => $client->id,
            'estimated_price' => $prix,
            'currency' => $devise,
        ]);

        return $client;
    }

    public function test_la_liste_porte_le_prix_et_la_devise(): void
    {
        $client = $this->clientAvecReservation(75.5, 'EUR');

        $reponse = $this->actingAs($client, 'sanctum')->getJson('/api/client/bookings')->assertOk();

        $reponse->assertJsonPath('data.0.estimated_price', 75.5);
        $reponse->assertJsonPath('data.0.currency', 'EUR');
        $this->assertArrayNotHasKey('total_price', $reponse->json('data.0'));
    }

    public function test_le_detail_porte_le_prix_et_la_devise(): void
    {
        $client = $this->clientAvecReservation(75.5, 'EUR');
        $reservation = Booking::query()->where('client_id', $client->id)->firstOrFail();

        $reponse = $this->actingAs($client, 'sanctum')
            ->getJson("/api/client/bookings/{$reservation->id}")
            ->assertOk();

        $reponse->assertJsonPath('data.estimated_price', 75.5);
        $reponse->assertJsonPath('data.currency', 'EUR');
        $this->assertArrayNotHasKey('total_price', $reponse->json('data'));
    }

    /**
     * TEMOIN — la devise n'est pas une constante deguisee.
     *
     * Sans ce controle, un `'currency' => 'EUR'` ecrit en dur passerait les deux tests
     * precedents : ils mesureraient une valeur figee, pas une donnee lue.
     */
    public function test_temoin_une_reservation_marocaine_remonte_ses_dirhams(): void
    {
        $client = $this->clientAvecReservation(750.0, 'MAD');

        $this->actingAs($client, 'sanctum')->getJson('/api/client/bookings')
            ->assertOk()
            ->assertJsonPath('data.0.currency', 'MAD');
    }
}
