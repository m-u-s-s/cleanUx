<?php

namespace Tests\Feature;

use App\Livewire\AdminDashboard;
use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Coverage for the AdminDashboard Livewire component and its admin concerns
 * (data computation, filtering, planning / replanification, preferences).
 */
class AdminDashboardComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function createGlobalAdmin(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    protected function seedDashboardData(): ServiceZone
    {
        $zone = ServiceZone::factory()->create([
            'coverage_type' => 'province',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
        ]);

        // A spread of statuses to exercise the many computed properties.
        Booking::factory()->create(['service_zone_id' => $zone->id, 'status' => 'en_attente']);
        Booking::factory()->confirme()->create(['service_zone_id' => $zone->id]);
        Booking::factory()->termine()->create([
            'service_zone_id' => $zone->id,
            'duree_estimee' => 90,
            'duree_reelle' => 120,
        ]);
        Booking::factory()->create([
            'service_zone_id' => $zone->id,
            'status' => 'en_attente',
            'priorite' => 'urgente',
            'created_at' => now()->subHours(6),
        ]);

        return $zone;
    }

    public function test_global_admin_can_mount_and_render_dashboard(): void
    {
        $this->seedDashboardData();
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->assertOk()
            ->assertSet('zoneScopeLocked', false)
            ->assertSet('realtimeEnabled', true);
    }

    public function test_refresh_and_realtime_toggle_actions(): void
    {
        $this->seedDashboardData();
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('refreshDashboard')
            ->assertDispatched('toast')
            ->call('realtimeRefresh')
            ->call('toggleRealtime')
            ->assertSet('realtimeEnabled', false)
            // When realtime is disabled, realtimeRefresh is a no-op guard branch.
            ->call('realtimeRefresh')
            ->call('toggleRealtime')
            ->assertSet('realtimeEnabled', true);
    }

    public function test_preference_toggles_and_reset(): void
    {
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('toggleCompactMode')
            ->assertSet('compactMode', false)
            ->call('toggleDashboardSection', 'analytics')
            ->assertSet('visibleDashboardSections.analytics', false)
            // Unknown section is ignored (guard branch).
            ->call('toggleDashboardSection', 'does-not-exist')
            ->call('toggleExecutiveMode')
            ->assertSet('executiveMode', true)
            ->call('resetDashboardPreferences')
            ->assertSet('executiveMode', false)
            ->assertSet('compactMode', false)
            ->assertSet('realtimeEnabled', true)
            ->assertSet('visibleDashboardSections.premium', true);
    }

    public function test_filters_update_and_reset(): void
    {
        $this->seedDashboardData();
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->set('filtreStatus', 'en_attente')
            ->set('filtrePeriode', 'today')
            ->set('dashboardSearch', 'nettoyage')
            ->call('resetDashboardFilters')
            ->assertSet('filtreStatus', '')
            ->assertSet('filtrePeriode', 'all')
            ->assertSet('dashboardSearch', '')
            ->assertSet('filtreEmploye', null)
            ->assertDispatched('toast');
    }

    public function test_mission_modal_open_and_close(): void
    {
        // Zone-less + employee-less booking and booking-free candidates keep the
        // suggestion computation on a clean path.
        User::factory()->employe()->create(['primary_service_zone_id' => null]);
        $booking = Booking::factory()->create([
            'service_zone_id' => null,
            'employe_id' => null,
            'status' => 'en_attente',
        ]);
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('ouvrirMission', $booking->id)
            ->assertSet('selectedMissionId', $booking->id)
            ->assertSet('showMissionModal', true)
            ->call('fermerMission')
            ->assertSet('selectedMissionId', null)
            ->assertSet('showMissionModal', false)
            ->assertSet('suggestedEmployees', []);
    }

    public function test_planning_modal_open_apply_suggestion_and_close(): void
    {
        $employe = User::factory()->employe()->create(['primary_service_zone_id' => null]);
        $booking = Booking::factory()->create([
            'service_zone_id' => null,
            'employe_id' => null,
            'status' => 'en_attente',
        ]);
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('ouvrirPlanning', $booking->id)
            ->assertSet('planningMissionId', $booking->id)
            ->assertSet('showPlanningModal', true)
            ->call('appliquerSuggestionEmploye', $employe->id)
            ->assertSet('planningEmployeId', $employe->id)
            ->set('planningDate', now()->addDays(3)->format('Y-m-d'))
            ->set('planningHeure', '11:00')
            ->call('fermerPlanning')
            ->assertSet('showPlanningModal', false)
            ->assertSet('planningMissionId', null);
    }

    public function test_enregistrer_planning_replans_mission(): void
    {
        Notification::fake();

        $newEmploye = User::factory()->employe()->create(['primary_service_zone_id' => null]);
        $booking = Booking::factory()->create([
            'service_zone_id' => null,
            'employe_id' => null,
            'status' => 'confirme',
        ]);
        $admin = $this->createGlobalAdmin();

        $newDate = now()->addDays(5)->format('Y-m-d');

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('ouvrirPlanning', $booking->id)
            ->set('planningEmployeId', $newEmploye->id)
            ->set('planningDate', $newDate)
            ->set('planningHeure', '14:30')
            ->call('enregistrerPlanning')
            ->assertHasNoErrors()
            ->assertSet('showPlanningModal', false);

        $booking->refresh();
        $this->assertSame($newEmploye->id, $booking->employe_id);
        $this->assertSame('14:30', substr((string) $booking->heure, 0, 5));
        // A confirmed mission is moved back to en_attente after replanification.
        $this->assertSame('en_attente', $booking->status);
    }

    public function test_enregistrer_planning_validates_required_fields(): void
    {
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('enregistrerPlanning')
            ->assertHasErrors(['planningMissionId', 'planningEmployeId', 'planningDate', 'planningHeure']);
    }

    public function test_navigation_actions_redirect(): void
    {
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('goToPlanning')
            ->assertRedirect(route('admin.planning'));

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('goToMissions')
            ->assertRedirect(route('admin.missions'));

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('goToFeedbacks')
            ->assertRedirect(route('admin.feedbacks'));
    }

    public function test_executive_mode_renders_summary_and_actions(): void
    {
        // Seed pending + aged-urgent bookings so the executive summary/actions branches fire.
        $this->seedDashboardData();
        $admin = $this->createGlobalAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->call('toggleExecutiveMode')
            ->assertSet('executiveMode', true)
            ->assertOk();
    }

    public function test_zone_scoped_admin_locks_zone_filter(): void
    {
        $zone = ServiceZone::factory()->create([
            'coverage_type' => 'province',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
        ]);
        Booking::factory()->create(['service_zone_id' => $zone->id, 'status' => 'en_attente']);

        $admin = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $zone->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->assertOk()
            ->assertSet('zoneScopeLocked', true)
            ->assertSet('filtreZone', (string) $zone->id)
            // updatedFiltreZone is a no-op when the zone scope is locked.
            ->set('filtreZone', '999')
            ->assertSet('filtreZone', '999');
    }
}
