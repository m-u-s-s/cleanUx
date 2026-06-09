<?php

namespace Tests\Feature\Missions;

use App\Events\MissionPositionUpdated;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionTrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeMissionTrackingControllerCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([MissionPositionUpdated::class]);
    }

    private function activeSessionFor(Mission $mission, User $employe): MissionTrackingSession
    {
        return MissionTrackingSession::query()->create([
            'mission_id' => $mission->id,
            'employee_user_id' => $employe->id,
            'tracking_mode' => 'to_client',
            'is_active' => true,
            'started_at' => now(),
            'last_lat' => 50.8503,
            'last_lng' => 4.3517,
            'point_count' => 0,
            'distance_meters' => 0,
        ]);
    }

    public function test_start_endpoint_starts_tracking_for_assigned_employee(): void
    {
        $employe = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create(['lead_employee_id' => null]);
        MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $employe->id,
        ]);

        Sanctum::actingAs($employe);

        $response = $this->postJson("/api/missions/{$mission->id}/tracking/start", [
            'lat' => 50.85,
            'lng' => 4.35,
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'en_route');

        $this->assertNotNull($response->json('session_id'));
        $this->assertSame('en_route', $mission->fresh()->status);
        $this->assertDatabaseHas('mission_tracking_sessions', [
            'id' => $response->json('session_id'),
            'mission_id' => $mission->id,
            'employee_user_id' => $employe->id,
        ]);
    }

    public function test_start_endpoint_works_for_lead_employee_without_assignment(): void
    {
        $employe = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => $employe->id,
        ]);

        Sanctum::actingAs($employe);

        $response = $this->postJson("/api/missions/{$mission->id}/tracking/start", [
            'lat' => 48.85,
            'lng' => 2.35,
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'en_route');
    }

    public function test_start_endpoint_forbidden_for_unassigned_employee(): void
    {
        $employe = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => null,
        ]);

        Sanctum::actingAs($employe);

        $this->postJson("/api/missions/{$mission->id}/tracking/start", [
            'lat' => 50.85,
            'lng' => 4.35,
        ])->assertForbidden();
    }

    public function test_start_endpoint_validates_coordinates(): void
    {
        $employe = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => $employe->id,
        ]);

        Sanctum::actingAs($employe);

        $this->postJson("/api/missions/{$mission->id}/tracking/start", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_push_endpoint_appends_point_and_returns_counters(): void
    {
        $employe = User::factory()->employe()->create();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
        ]);
        $session = $this->activeSessionFor($mission, $employe);

        Sanctum::actingAs($employe);

        $response = $this->postJson("/api/mission-tracking-sessions/{$session->id}/push", [
            'lat' => 50.8600,
            'lng' => 4.3600,
            'accuracy_meters' => 8.5,
            'speed_kmh' => 40,
            'heading' => 90,
            'battery_level' => 80,
            'source' => 'mobile',
            'app_state' => 'foreground',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('point_count', 1);
        $this->assertGreaterThan(0, $response->json('distance_meters'));
    }

    public function test_push_endpoint_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->employe()->create();
        $stranger = User::factory()->employe()->create();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $owner->id,
        ]);
        $session = $this->activeSessionFor($mission, $owner);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/mission-tracking-sessions/{$session->id}/push", [
            'lat' => 50.86,
            'lng' => 4.36,
        ])->assertForbidden();
    }

    public function test_push_endpoint_validates_coordinates(): void
    {
        $employe = User::factory()->employe()->create();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
        ]);
        $session = $this->activeSessionFor($mission, $employe);

        Sanctum::actingAs($employe);

        $this->postJson("/api/mission-tracking-sessions/{$session->id}/push", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_stop_endpoint_ends_session_with_final_coordinates(): void
    {
        $employe = User::factory()->employe()->create();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
        ]);
        $session = $this->activeSessionFor($mission, $employe);

        Sanctum::actingAs($employe);

        $response = $this->postJson("/api/mission-tracking-sessions/{$session->id}/stop", [
            'lat' => 50.90,
            'lng' => 4.40,
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertNotNull($response->json('ended_at'));

        $session->refresh();
        $this->assertFalse((bool) $session->is_active);
        $this->assertNotNull($session->ended_at);
        $this->assertEquals(50.90, (float) $session->last_lat);
    }

    public function test_stop_endpoint_ends_session_without_coordinates(): void
    {
        $employe = User::factory()->employe()->create();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
        ]);
        $session = $this->activeSessionFor($mission, $employe);

        Sanctum::actingAs($employe);

        $response = $this->postJson("/api/mission-tracking-sessions/{$session->id}/stop", []);

        $response->assertOk();
        $response->assertJsonPath('ok', true);

        $session->refresh();
        $this->assertFalse((bool) $session->is_active);
        $this->assertEquals(50.8503, (float) $session->last_lat);
    }

    public function test_stop_endpoint_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->employe()->create();
        $stranger = User::factory()->employe()->create();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $owner->id,
        ]);
        $session = $this->activeSessionFor($mission, $owner);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/mission-tracking-sessions/{$session->id}/stop", [])
            ->assertForbidden();
    }
}
