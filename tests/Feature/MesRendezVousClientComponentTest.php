<?php

namespace Tests\Feature;

use App\Livewire\Client\MesRendezVousClient;
use App\Support\Domain\BookingStatus;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

class MesRendezVousClientComponentTest extends TestCase
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
                [
                    'lat' => '50.8466',
                    'lon' => '4.3528',
                    'display_name' => 'Rue de Test 1, 1000 Bruxelles, Belgique',
                ],
            ], 200),
        ]);
    }

    public function test_client_can_render_and_filter_their_rendez_vous_list(): void
    {
        $scenario = $this->createMissionPortalContext();

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->assertOk()
            ->set('search', 'Rue de Test')
            ->set('filtreStatus', BookingStatus::CONFIRME)
            ->set('tri', 'desc')
            ->set('dateFrom', now()->subDay()->toDateString())
            ->set('dateTo', now()->addDays(30)->toDateString())
            ->assertOk();
    }

    public function test_modifier_opens_edition_panel_and_loads_available_slots(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        $component = Livewire::test(MesRendezVousClient::class)
            ->call('modifier', $rdv->id)
            ->assertSet('editRdvId', $rdv->id)
            ->assertSet('editHeure', '10:00')
            ->assertSet('impactDevisMessage', 'Le devis reste inchangé pour ce changement de créneau.');

        $this->assertNotEmpty($component->get('creneauxDisponibles'));

        // updatedEditDate hook re-loads slots
        $component->set('editDate', $rdv->date->format('Y-m-d'))
            ->assertOk();

        // fermerEdition resets the panel
        $component->call('fermerEdition')
            ->assertSet('editRdvId', null)
            ->assertSet('editDate', null)
            ->assertSet('editHeure', null);
    }

    public function test_enregistrer_modif_replanifies_the_rendez_vous(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];
        $date = $rdv->date->format('Y-m-d');

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->call('modifier', $rdv->id)
            ->set('editHeure', '14:00')
            ->call('enregistrerModif')
            ->assertHasNoErrors()
            ->assertSet('editRdvId', null);

        $fresh = $rdv->fresh();
        $this->assertSame('14:00', substr((string) $fresh->heure, 0, 5));
        $this->assertSame($date, $fresh->date->format('Y-m-d'));
        $this->assertSame(BookingStatus::EN_ATTENTE, $fresh->status);
    }

    public function test_enregistrer_modif_validates_required_fields(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->call('modifier', $rdv->id)
            ->set('editDate', '')
            ->set('editHeure', '')
            ->call('enregistrerModif')
            ->assertHasErrors(['editDate', 'editHeure']);
    }

    public function test_annuler_opens_the_cancellation_panel(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->call('annuler', $rdv->id)
            ->assertSet('cancelRdvId', $rdv->id)
            ->assertSet('cancelReason', '');
    }

    public function test_fermer_annulation_resets_cancel_state(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->call('demanderAnnulation', $rdv->id)
            ->set('cancelReason', 'test')
            ->call('fermerAnnulation')
            ->assertSet('cancelRdvId', null)
            ->assertSet('cancelReason', '');
    }

    public function test_locked_rendez_vous_cannot_be_modified_or_cancelled(): void
    {
        $scenario = $this->createMissionPortalContext([
            'status' => 'in_progress',
        ], ['status' => BookingStatus::TERMINE]);

        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        // Policy denies update/cancel for non-editable statuses -> authorization error
        Livewire::test(MesRendezVousClient::class)
            ->call('modifier', $rdv->id)
            ->assertForbidden();

        Livewire::test(MesRendezVousClient::class)
            ->call('demanderAnnulation', $rdv->id)
            ->assertForbidden();
    }

    public function test_history_for_returns_recent_activity_entries(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        // Replanify to generate an activity log entry, then read history back.
        Livewire::test(MesRendezVousClient::class)
            ->call('modifier', $rdv->id)
            ->set('editHeure', '15:00')
            ->call('enregistrerModif')
            ->assertHasNoErrors();

        $history = Livewire::test(MesRendezVousClient::class)
            ->instance()
            ->historyFor($rdv->id);

        $this->assertNotNull($history);
        $this->assertGreaterThanOrEqual(1, $history->count());
    }
}
