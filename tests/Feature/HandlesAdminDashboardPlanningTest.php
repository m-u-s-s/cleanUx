<?php

namespace Tests\Feature;

use App\Livewire\AdminDashboard;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\MissionReplanifieeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class HandlesAdminDashboardPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_ouvrir_mission_opens_modal_and_computes_suggestions(): void
    {
        Notification::fake();

        // Unassigned mission: no candidate has a same-day booking, so scoring runs clean.
        $rdv = Booking::factory()->confirme()->create([
            'service_zone_id' => null,
            'employe_id' => null,
            'date' => now()->addDays(3)->toDateString(),
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
        ]);

        // A free employee so the suggestion list is non-empty (no zone restriction).
        User::factory()->employe()->create([
            'name' => 'Employe Disponible',
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->assertOk()
            ->call('ouvrirMission', $rdv->id)
            ->assertSet('selectedMissionId', $rdv->id)
            ->assertSet('showMissionModal', true);

        $this->assertNotEmpty($component->get('suggestedEmployees'));

        // Closing resets state.
        $component->call('fermerMission')
            ->assertSet('selectedMissionId', null)
            ->assertSet('showMissionModal', false)
            ->assertSet('suggestedEmployees', []);
    }

    public function test_ouvrir_planning_hydrates_fields_and_reacts_to_changes(): void
    {
        Notification::fake();

        $rdv = Booking::factory()->confirme()->create([
            'service_zone_id' => null,
            'employe_id' => null,
            'date' => now()->addDays(4)->toDateString(),
            'heure' => '09:30:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
        ]);

        $suggested = User::factory()->employe()->create([
            'name' => 'Employe Suggere',
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->call('ouvrirPlanning', $rdv->id)
            ->assertSet('planningMissionId', $rdv->id)
            ->assertSet('planningEmployeId', $rdv->employe_id)
            ->assertSet('showPlanningModal', true);

        // updatedPlanningDate / updatedPlanningHeure recompute suggestions.
        $component->set('planningDate', now()->addDays(5)->toDateString())
            ->set('planningHeure', '11:00');

        // Applying a suggestion swaps the chosen employee.
        $component->call('appliquerSuggestionEmploye', $suggested->id)
            ->assertSet('planningEmployeId', $suggested->id);

        // Closing the planning modal resets the working fields.
        $component->call('fermerPlanning')
            ->assertSet('showPlanningModal', false)
            ->assertSet('planningMissionId', null)
            ->assertSet('planningEmployeId', null)
            ->assertSet('suggestedEmployees', []);
    }

    public function test_enregistrer_planning_replans_mission_successfully(): void
    {
        Notification::fake();

        $rdv = Booking::factory()->confirme()->create([
            'service_zone_id' => null,
            'employe_id' => null,
            'date' => now()->addDays(6)->toDateString(),
            'heure' => '10:00:00',
            'duree' => 90,
            'estimated_duration_minutes' => 90,
        ]);

        $newEmploye = User::factory()->employe()->create([
            'name' => 'Nouveau Technicien',
        ]);

        $newDate = now()->addDays(7)->toDateString();

        Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->call('ouvrirPlanning', $rdv->id)
            ->set('planningEmployeId', $newEmploye->id)
            ->set('planningDate', $newDate)
            ->set('planningHeure', '14:00')
            ->call('enregistrerPlanning')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('showPlanningModal', false);

        $rdv->refresh();

        $this->assertSame($newEmploye->id, $rdv->employe_id);
        $this->assertSame($newDate, $rdv->date?->format('Y-m-d') ?? (string) $rdv->date);
        // confirme -> en_attente on replanification.
        $this->assertSame('en_attente', $rdv->status);

        // The reassigned employee was notified.
        Notification::assertSentTo($newEmploye, MissionReplanifieeNotification::class);
    }

    public function test_enregistrer_planning_validates_required_fields(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->call('fermerPlanning')
            ->call('enregistrerPlanning')
            ->assertHasErrors([
                'planningMissionId',
                'planningEmployeId',
                'planningDate',
                'planningHeure',
            ]);
    }

    public function test_premium_and_unassigned_computed_properties_render(): void
    {
        Notification::fake();

        $premiumClient = User::factory()->premiumClient()->create(['name' => 'Client Premium']);
        User::factory()->client()->create(['name' => 'Client Standard']);

        // Premium mission surfaces in premiumRendezVous.
        Booking::factory()->confirme()->create([
            'client_id' => $premiumClient->id,
            'date' => now()->addDays(2)->toDateString(),
            'heure' => '08:00:00',
        ]);

        // Unassigned, pending mission surfaces in rendezVousSansEmploye.
        Booking::factory()->enAttente()->create([
            'employe_id' => null,
            'date' => now()->addDays(2)->toDateString(),
            'heure' => '13:30:00',
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->assertOk();

        $this->assertSame(1, $component->get('premiumClientsCount'));
        $this->assertGreaterThanOrEqual(1, $component->get('standardClientsCount'));
        $this->assertSame(1, $component->get('activeSubscriptionsCount'));
        $this->assertNotEmpty($component->get('premiumClients'));
        $this->assertNotEmpty($component->get('premiumRendezVous'));
        $this->assertNotEmpty($component->get('rendezVousSansEmploye'));
        // Premium client with no favorite employees attached is surfaced.
        $this->assertNotEmpty($component->get('premiumClientsWithoutFavorites'));
    }
}
