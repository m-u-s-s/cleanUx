<?php

namespace Tests\Feature\Missions;

use App\Livewire\Client\GererMaMission;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionQuoteRevisionService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** « GÉRER MA MISSION » SUR LE WEB — la parité que le porteur demande. */
class GererMaMissionWebTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $prestataire;

    private function mission(): Mission
    {
        $this->client = User::factory()->client()->create();
        $this->prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $this->prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $this->client->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'devis_estime' => 50.00,
            'estimated_price' => 50.00,
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->prestataire->id,
            'status' => MissionStatus::ARRIVED,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $this->prestataire->id,
        ]);

        return $mission->fresh('booking');
    }

    public function test_le_client_ajoute_une_tache_depuis_le_web(): void
    {
        $mission = $this->mission();

        Livewire::actingAs($this->client)
            ->test(GererMaMission::class, ['booking' => $mission->booking])
            ->set('nouvelleTache', 'Nettoyer la hotte')
            ->call('ajouterUneTache')
            ->assertSet('erreur', null)
            ->assertSee('Nettoyer la hotte');
    }

    /** LE TÉMOIN de la garde : le composant est une porte HTTP à part entière. */
    public function test_un_tiers_n_ouvre_pas_le_panneau(): void
    {
        $mission = $this->mission();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(GererMaMission::class, ['booking' => $mission->booking])
            ->assertForbidden();
    }

    public function test_le_client_voit_les_deux_totaux_de_la_revision(): void
    {
        $mission = $this->mission();

        app(MissionQuoteRevisionService::class)
            ->proposer($mission, $this->prestataire, 30000, 'Deux cents mètres carrés.', [1]);

        Livewire::actingAs($this->client)
            ->test(GererMaMission::class, ['booking' => $mission->booking])
            ->assertSee('Nouveau devis proposé')
            ->assertSee('300,00')
            ->assertSee('50,00')
            ->assertSee('Deux cents mètres carrés.');
    }

    public function test_le_client_refuse_et_continue_depuis_le_web(): void
    {
        $mission = $this->mission();

        $revision = app(MissionQuoteRevisionService::class)
            ->proposer($mission, $this->prestataire, 30000, 'Plus grand', [1]);

        Livewire::actingAs($this->client)
            ->test(GererMaMission::class, ['booking' => $mission->booking])
            ->call('refuserLaRevision', $revision->id, 'continue')
            ->assertSet('erreur', null);

        $this->assertSame('declined', $revision->fresh()->status);
        $this->assertSame(50.0, (float) $mission->booking->fresh()->devis_estime);
    }

    /** LA CONSIGNE DE DERNIÈRE MINUTE N'A PAS DE FENÊTRE, contrairement à la liste : un digicode qui change à 17 h doit pouvoir se dire à 17 h. */
    public function test_le_client_pose_une_consigne_de_derniere_minute(): void
    {
        $mission = $this->mission();

        Livewire::actingAs($this->client)
            ->test(GererMaMission::class, ['booking' => $mission->booking])
            ->set('consigne', 'Le digicode est 4589.')
            ->call('enregistrerLaConsigne')
            ->assertSet('erreur', null);

        $booking = $mission->booking->fresh();

        $this->assertSame('Le digicode est 4589.', $booking->live_access_note);
        $this->assertNotNull($booking->live_access_note_at, 'une consigne périme : on garde sa date');
    }

    /** LE TÉMOIN : une chaîne vide EFFACE — le client s'est trompé, ou la situation est revenue. */
    public function test_une_consigne_vide_efface(): void
    {
        $mission = $this->mission();
        $mission->booking->forceFill([
            'live_access_note' => 'Ancien code',
            'live_access_note_at' => now(),
        ])->save();

        Livewire::actingAs($this->client)
            ->test(GererMaMission::class, ['booking' => $mission->booking->fresh()])
            ->set('consigne', '  ')
            ->call('enregistrerLaConsigne');

        $this->assertNull($mission->booking->fresh()->live_access_note);
    }

    /** L'identifiant vient du navigateur : une révision d'une autre réservation est refusée. */
    public function test_une_revision_etrangere_est_refusee(): void
    {
        $mission = $this->mission();
        $autre = $this->mission();

        $revision = app(MissionQuoteRevisionService::class)
            ->proposer($autre, $this->prestataire, 30000, 'Plus grand', [1]);

        Livewire::actingAs($mission->booking->client)
            ->test(GererMaMission::class, ['booking' => $mission->booking])
            ->call('refuserLaRevision', $revision->id, 'continue')
            ->assertSet('erreur', 'Cette proposition ne concerne pas votre réservation.');
    }
}
