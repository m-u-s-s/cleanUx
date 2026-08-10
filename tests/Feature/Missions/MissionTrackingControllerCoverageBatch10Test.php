<?php

namespace Tests\Feature\Missions;

use App\Events\MissionPositionUpdated;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionTrackingSession;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MissionTrackingControllerCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([MissionPositionUpdated::class]);
    }

    private function makeEmploye(): User
    {
        $employe = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $employe->id, 'status' => 'active']);

        return $employe;
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
        $employe = $this->makeEmploye();
        $mission = Mission::factory()->assigned()->create(['lead_employee_id' => null]);
        MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $employe->id,
        ]);

        $response = $this->actingAs($employe)
            ->postJson("/missions/{$mission->id}/tracking/start", [
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
        $employe = $this->makeEmploye();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => $employe->id,
        ]);

        $response = $this->actingAs($employe)
            ->postJson("/missions/{$mission->id}/tracking/start", [
                'lat' => 48.85,
                'lng' => 2.35,
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'en_route');
    }

    public function test_start_endpoint_validates_coordinates(): void
    {
        $employe = $this->makeEmploye();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => $employe->id,
        ]);

        $this->actingAs($employe)
            ->postJson("/missions/{$mission->id}/tracking/start", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_push_endpoint_appends_point_and_returns_counters(): void
    {
        $employe = $this->makeEmploye();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
        ]);
        $session = $this->activeSessionFor($mission, $employe);

        $response = $this->actingAs($employe)
            ->postJson("/mission-tracking-sessions/{$session->id}/tracking/push", [
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
        $owner = $this->makeEmploye();
        $stranger = $this->makeEmploye();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $owner->id,
        ]);
        $session = $this->activeSessionFor($mission, $owner);

        $this->actingAs($stranger)
            ->postJson("/mission-tracking-sessions/{$session->id}/tracking/push", [
                'lat' => 50.86,
                'lng' => 4.36,
            ])
            ->assertForbidden();
    }

    public function test_push_endpoint_validates_coordinates(): void
    {
        $employe = $this->makeEmploye();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
        ]);
        $session = $this->activeSessionFor($mission, $employe);

        $this->actingAs($employe)
            ->postJson("/mission-tracking-sessions/{$session->id}/tracking/push", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_stop_endpoint_ends_session(): void
    {
        $employe = $this->makeEmploye();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
        ]);
        $session = $this->activeSessionFor($mission, $employe);

        $response = $this->actingAs($employe)
            ->postJson("/mission-tracking-sessions/{$session->id}/tracking/stop", [
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

    public function test_stop_endpoint_forbidden_for_non_owner(): void
    {
        $owner = $this->makeEmploye();
        $stranger = $this->makeEmploye();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $owner->id,
        ]);
        $session = $this->activeSessionFor($mission, $owner);

        $this->actingAs($stranger)
            ->postJson("/mission-tracking-sessions/{$session->id}/tracking/stop", [])
            ->assertForbidden();
    }

    public function test_live_endpoint_returns_payload_for_client_owner(): void
    {
        $client = User::factory()->client()->create();
        $employe = $this->makeEmploye();

        $booking = Booking::factory()->create(['client_id' => $client->id]);
        $mission = Mission::factory()->enRoute()->create([
            'booking_id' => $booking->id,
            'lead_employee_id' => $employe->id,
            'organization_account_id' => null,
            'destination_lat' => 50.9000,
            'destination_lng' => 4.4000,
        ]);
        $this->activeSessionFor($mission, $employe);

        $response = $this->actingAs($client)
            ->getJson("/missions/{$mission->id}/tracking/live");

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.mission_id', (int) $mission->id);
        $response->assertJsonPath('data.status', 'en_route');
    }

    public function test_live_endpoint_forbidden_for_unrelated_admin(): void
    {
        $admin = User::factory()->admin()->create(['organization_account_id' => null]);
        $employe = $this->makeEmploye();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
            'organization_account_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson("/missions/{$mission->id}/tracking/live")
            ->assertForbidden();
    }

    public function test_live_endpoint_allows_admin_with_matching_organization(): void
    {
        $org = OrganizationAccount::factory()->create();
        $admin = User::factory()->admin()->create([
            'organization_account_id' => $org->id,
        ]);
        $employe = $this->makeEmploye();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employe->id,
            'organization_account_id' => $org->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/missions/{$mission->id}/tracking/live");

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('data.mission_id', (int) $mission->id);
    }
}
