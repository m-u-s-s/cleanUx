<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\MesRendezVousClient;
use App\Support\Domain\BookingStatus;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

class MesRendezVousClientCoverageBatch18Test extends TestCase
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

    public function test_charger_creneaux_returns_empty_when_no_edition_in_progress(): void
    {
        $scenario = $this->createMissionPortalContext();

        $this->actingAs($scenario['client']);

        $component = Livewire::test(MesRendezVousClient::class)
            ->call('chargerCreneauxDisponibles')
            ->assertSet('creneauxDisponibles', []);

        $this->assertSame([], $component->get('creneauxDisponibles'));
    }

    public function test_modifier_loads_slots_and_updated_edit_date_reloads_them(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        $component = Livewire::test(MesRendezVousClient::class)
            ->call('modifier', $rdv->id)
            ->assertSet('editRdvId', $rdv->id)
            ->assertSet('editHeure', '10:00')
            ->assertSet('employeReplanificationMessage', null)
            ->assertSet('impactDevisMessage', 'Le devis reste inchangé pour ce changement de créneau.');

        $this->assertNotEmpty($component->get('creneauxDisponibles'));

        // The updatedEditDate hook recomputes the available slots.
        $component->set('editDate', $rdv->date->format('Y-m-d'))->assertOk();
        $this->assertNotEmpty($component->get('creneauxDisponibles'));

        $component->call('fermerEdition')
            ->assertSet('editRdvId', null)
            ->assertSet('editDate', null)
            ->assertSet('editHeure', null);
    }

    public function test_enregistrer_modif_replanifies_and_records_activity(): void
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

    public function test_enregistrer_modif_rejects_invalid_slot_inputs(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->call('modifier', $rdv->id)
            ->set('editDate', now()->subDays(5)->toDateString())
            ->set('editHeure', 'not-a-time')
            ->call('enregistrerModif')
            ->assertHasErrors(['editDate', 'editHeure']);
    }

    public function test_demander_and_fermer_annulation_toggle_the_cancel_panel(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->call('annuler', $rdv->id)
            ->assertSet('cancelRdvId', $rdv->id)
            ->assertSet('cancelReason', '')
            ->set('cancelReason', 'changement de plan')
            ->call('fermerAnnulation')
            ->assertSet('cancelRdvId', null)
            ->assertSet('cancelReason', '');
    }

    public function test_history_for_returns_recent_activity_after_replanification(): void
    {
        $scenario = $this->createMissionPortalContext();
        $rdv = $scenario['rendezVous'];

        $this->actingAs($scenario['client']);

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

    public function test_filters_and_sort_drive_the_query_branches(): void
    {
        $scenario = $this->createMissionPortalContext();

        $this->actingAs($scenario['client']);

        Livewire::test(MesRendezVousClient::class)
            ->set('filtreStatus', BookingStatus::CONFIRME)
            ->set('search', 'Rue de Test')
            ->set('dateFrom', now()->subDay()->toDateString())
            ->set('dateTo', now()->addDays(30)->toDateString())
            ->set('tri', 'desc')
            ->assertOk()
            ->assertViewHas('rendezVous');
    }
}
