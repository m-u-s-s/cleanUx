<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\Calendar\ClientCalendarFC;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le panneau de détail du calendrier client affiche l'adresse du lieu
 * d'intervention — donc, sur cette plateforme, le domicile de quelqu'un.
 *
 * `selectEvent()` est appelable depuis le navigateur avec n'importe quel
 * identifiant. La lecture doit donc revérifier l'appartenance, et pas se
 * reposer sur le fait que le calendrier n'affiche que les réservations
 * de l'utilisateur.
 *
 * Les deux cas vont ensemble : sans le témoin positif, le test de refus
 * passerait au vert en mesurant un composant qui ne rend jamais rien.
 */
class ClientCalendarFCAppartenanceTest extends TestCase
{
    use RefreshDatabase;

    private function reservationDe(User $client, string $adresse): Booking
    {
        return Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'address' => $adresse,
            'postal_code' => '1000',
            'city' => 'Bruxelles',
        ]);
    }

    /** TÉMOIN POSITIF — le propriétaire voit bien sa propre réservation. */
    public function test_le_proprietaire_voit_sa_reservation(): void
    {
        $client = User::factory()->create();
        $reservation = $this->reservationDe($client, '12 rue du Témoin');

        Livewire::actingAs($client)
            ->test(ClientCalendarFC::class)
            ->call('selectEvent', $reservation->id)
            ->assertSet('selectedBookingId', $reservation->id)
            ->assertSee('12 rue du Témoin');
    }

    /** REFUS — un autre client n'atteint pas l'adresse d'autrui. */
    public function test_un_tiers_n_atteint_pas_la_reservation_d_autrui(): void
    {
        $victime = User::factory()->create();
        $curieux = User::factory()->create();
        $reservation = $this->reservationDe($victime, '99 avenue Confidentielle');

        Livewire::actingAs($curieux)
            ->test(ClientCalendarFC::class)
            ->call('selectEvent', $reservation->id)
            ->assertDontSee('99 avenue Confidentielle')
            ->assertDontSee('Bruxelles');
    }

    /** La propriété ne doit pas être retournable directement depuis le navigateur. */
    public function test_la_propriete_est_verrouillee(): void
    {
        $curieux = User::factory()->create();
        $victime = User::factory()->create();
        $reservation = $this->reservationDe($victime, '7 impasse Verrouillée');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($curieux)
            ->test(ClientCalendarFC::class)
            ->set('selectedBookingId', $reservation->id);
    }
}
