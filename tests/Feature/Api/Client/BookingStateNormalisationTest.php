<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * L'API expose un état normalisé, en plus du statut brut.
 *
 * Le domaine emploie un vocabulaire FRANÇAIS — en_attente, confirme, en_route, sur_place,
 * termine, annule — avec des variantes anglaises tolérées pour rétrocompatibilité. Les données
 * réelles mélangent les deux : la base porte aujourd'hui `confirmed` ET `en_attente`.
 *
 * Les applications mobiles filtraient sur des chaînes anglaises devinées. Conséquences : une
 * réservation `en_attente` n'était jamais comptée comme active, et `en_route` ou `sur_place`
 * n'étaient jamais reconnues comme en cours — si bien que la carte de suivi de l'accueil client
 * ne s'affichait JAMAIS, quelle que soit la mission.
 *
 * Le vocabulaire se traduit désormais une seule fois, côté serveur, en réutilisant les aides du
 * modèle qui connaissent déjà les deux formes.
 */
class BookingStateNormalisationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function statusMap(): array
    {
        return [
            'français en attente' => ['en_attente', 'pending'],
            'français confirmé' => ['confirme', 'confirmed'],
            // Les deux statuts qui portent une mission en cours, et que l'application ne
            // reconnaissait pas : c'est précisément ce qui empêchait la carte de s'afficher.
            'français en route' => ['en_route', 'in_progress'],
            'français sur place' => ['sur_place', 'in_progress'],
            'français terminé' => ['termine', 'completed'],
            'français annulé' => ['annule', 'cancelled'],
            'anglais confirmed' => ['confirmed', 'confirmed'],
            'anglais in_progress' => ['in_progress', 'in_progress'],
            'anglais completed' => ['completed', 'completed'],
        ];
    }

    #[DataProvider('statusMap')]
    public function test_it_normalises_each_status(string $rawStatus, string $expectedState): void
    {
        $client = $this->clientWithBooking($rawStatus);

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/client/bookings')->assertOk();

        $this->assertSame($expectedState, $response->json('data.0.state'));
    }

    /** Le statut brut reste exposé : l'affichage et les filtres existants s'y appuient. */
    public function test_the_raw_status_is_preserved(): void
    {
        $client = $this->clientWithBooking('en_route');

        $this->actingAs($client, 'sanctum')->getJson('/api/client/bookings')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'en_route');
    }

    private function clientWithBooking(string $status): User
    {
        $client = User::factory()->client()->create();

        Booking::factory()->create([
            'client_id' => $client->id,
            'status' => $status,
        ]);

        return $client;
    }
}
