<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\MesRendezVousClient;
use App\Livewire\ClientDashboard;
use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Support\Domain\BookingStatus;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

/**
 * L'ANNULATION CLIENT N'ABOUTISSAIT NULLE PART.
 *
 * Les deux ecrans appelaient `Booking::markCancelledByClient()` et
 * `CancellationEngine::cancel()` : aucune des deux methodes n'existe.
 */
class LAnnulationClientAboutitTest extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CancellationPoliciesSeeder::class);
        Config::set('cancellation_v2.enabled', true);
        Config::set('cancellation_v2.default_refund_method', 'mock');
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '50.8466', 'lon' => '4.3528', 'display_name' => 'Rue de Test 1, 1000 Bruxelles'],
            ], 200),
        ]);
    }

    /** Le bouton du tableau de bord OUVRE le devis ; il ne preleve rien de lui-meme. */
    public function test_le_tableau_de_bord_annonce_les_frais_avant_d_annuler(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        Livewire::actingAs($scenario['client'])->test(ClientDashboard::class)
            ->call('annuler', $rdv->id)
            ->assertSet('cancelRdvId', $rdv->id)
            ->assertSee('annulation', escape: false);

        $this->assertNotSame(BookingStatus::ANNULE, Booking::find($rdv->id)->status,
            'Le seul clic sur « Annuler » a deja annule : les frais ne sont jamais annonces.');
    }

    public function test_le_tableau_de_bord_annule_apres_confirmation(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        Livewire::actingAs($scenario['client'])->test(ClientDashboard::class)
            ->call('annuler', $rdv->id)
            ->call('confirmerAnnulation')
            ->assertHasNoErrors();

        $this->assertSame(BookingStatus::ANNULE, Booking::find($rdv->id)->status);
        $this->assertDatabaseHas('booking_cancellations_v2', [
            'booking_id' => $rdv->id,
            'actor_role' => 'client',
        ]);
    }

    /** Le chemin de confirmation de « Mes rendez-vous » : aucun test ne l'atteignait. */
    public function test_mes_rendez_vous_annule_apres_confirmation(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        Livewire::actingAs($scenario['client'])->test(MesRendezVousClient::class)
            ->call('demanderAnnulation', $rdv->id)
            ->set('cancelReason', 'Empeche ce jour-la')
            ->call('confirmerAnnulation')
            ->assertHasNoErrors();

        $this->assertSame(BookingStatus::ANNULE, Booking::find($rdv->id)->status);
        $this->assertSame(1, BookingCancellationV2::where('booking_id', $rdv->id)->count());
    }

    /**
     * REFUS — un rendez-vous deja termine ne s'annule pas, ni par le bouton, ni par la modale.
     *
     * Le TEMOIN POSITIF est `test_le_tableau_de_bord_annule_apres_confirmation` : sans lui, ce
     * refus resterait vert alors que l'annulation est cassee pour TOUS les rendez-vous.
     */
    public function test_un_rendez_vous_termine_ne_s_annule_pas(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];
        $rdv->forceFill(['status' => BookingStatus::TERMINE])->save();

        Livewire::actingAs($scenario['client'])->test(ClientDashboard::class)
            ->call('annuler', $rdv->id)
            ->assertForbidden();

        // La porte de derriere : forger l'identifiant sans passer par le bouton.
        Livewire::actingAs($scenario['client'])->test(ClientDashboard::class)
            ->set('cancelRdvId', $rdv->id)
            ->call('confirmerAnnulation')
            ->assertForbidden();

        $this->assertSame(BookingStatus::TERMINE, Booking::find($rdv->id)->status);
        $this->assertDatabaseMissing('booking_cancellations_v2', ['booking_id' => $rdv->id]);
    }

    /** `cancelRdvId` est une propriete PUBLIQUE : le navigateur peut la retourner par `$set`. */
    public function test_un_client_ne_peut_pas_confirmer_l_annulation_d_un_autre(): void
    {
        $mien = $this->createMissionPortalContext();
        $sien = $this->createMissionPortalContext();

        Livewire::actingAs($mien['client'])->test(ClientDashboard::class)
            ->set('cancelRdvId', $sien['rendezVous']->id)
            ->call('confirmerAnnulation')
            ->assertForbidden();

        $this->assertNotSame(BookingStatus::ANNULE, Booking::find($sien['rendezVous']->id)->status);
    }
}
