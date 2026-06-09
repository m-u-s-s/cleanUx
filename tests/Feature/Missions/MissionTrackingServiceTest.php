<?php

namespace Tests\Feature\Missions;

use App\Events\MissionPositionUpdated;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionTrackingPoint;
use App\Models\MissionTrackingSession;
use App\Models\User;
use App\Services\Missions\MissionTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class MissionTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MissionTrackingService
    {
        return app(MissionTrackingService::class);
    }

    // ──────────────────────────────────────────────
    // startToClientTracking
    // ──────────────────────────────────────────────

    public function test_start_to_client_tracking_creates_session_seeds_point_and_sets_en_route(): void
    {
        Event::fake([MissionPositionUpdated::class]);

        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => $employee->id,
        ]);

        $session = $this->service()->startToClientTracking($mission, $employee, 50.85, 4.35);

        $this->assertTrue($session->is_active);
        $this->assertSame('to_client', $session->tracking_mode);
        $this->assertSame($employee->id, $session->employee_user_id);
        $this->assertEquals(50.85, (float) $session->start_lat);
        $this->assertEquals(4.35, (float) $session->last_lng);

        // One initial point was stored by storePoint().
        $this->assertCount(1, $session->points);
        $this->assertSame('en_route', $mission->fresh()->status);
    }

    public function test_start_to_client_tracking_links_assignment_when_employee_is_assigned(): void
    {
        Event::fake([MissionPositionUpdated::class]);

        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => null,
        ]);
        $assignment = MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $employee->id,
        ]);

        $session = $this->service()->startToClientTracking($mission, $employee, 48.85, 2.35);

        $this->assertSame($assignment->id, $session->assignment_id);
    }

    public function test_start_to_client_tracking_deactivates_previous_active_session(): void
    {
        Event::fake([MissionPositionUpdated::class]);

        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => $employee->id,
        ]);

        $previous = MissionTrackingSession::query()->create([
            'mission_id' => $mission->id,
            'employee_user_id' => $employee->id,
            'tracking_mode' => 'to_client',
            'is_active' => true,
            'started_at' => now(),
        ]);

        $this->service()->startToClientTracking($mission, $employee, 50.0, 4.0);

        $this->assertFalse($previous->fresh()->is_active);
        $this->assertNotNull($previous->fresh()->ended_at);
        $this->assertSame(1, MissionTrackingSession::query()->where('is_active', true)->count());
    }

    public function test_start_to_client_tracking_rejects_invalid_mission_status(): void
    {
        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->completed()->create([
            'lead_employee_id' => $employee->id,
        ]);

        $this->expectException(RuntimeException::class);

        $this->service()->startToClientTracking($mission, $employee, 1.0, 1.0);
    }

    public function test_start_to_client_tracking_rejects_non_employee_user(): void
    {
        $client = User::factory()->client()->create();
        $mission = Mission::factory()->assigned()->create();

        $this->expectException(RuntimeException::class);

        $this->service()->startToClientTracking($mission, $client, 1.0, 1.0);
    }

    public function test_start_to_client_tracking_rejects_unassigned_employee(): void
    {
        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => null,
        ]);

        $this->expectException(RuntimeException::class);

        $this->service()->startToClientTracking($mission, $employee, 1.0, 1.0);
    }

    // ──────────────────────────────────────────────
    // pushPoint
    // ──────────────────────────────────────────────

    public function test_push_point_increments_counters_accumulates_distance_and_dispatches_event(): void
    {
        Event::fake([MissionPositionUpdated::class]);

        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->assigned()->create([
            'lead_employee_id' => $employee->id,
        ]);

        $session = $this->service()->startToClientTracking($mission, $employee, 50.8503, 4.3517);

        $updated = $this->service()->pushPoint($session, [
            'lat' => 50.8600,
            'lng' => 4.3600,
            'speed_kmh' => 40,
            'source' => 'mobile',
        ]);

        $this->assertSame(1, $updated->point_count);
        $this->assertGreaterThan(0, $updated->distance_meters);
        $this->assertEquals(50.86, (float) $updated->last_lat);
        $this->assertCount(2, $updated->points);

        Event::assertDispatched(MissionPositionUpdated::class, function (MissionPositionUpdated $event) use ($mission) {
            return $event->missionId === (int) $mission->id;
        });
    }

    public function test_push_point_rejects_inactive_session(): void
    {
        $session = MissionTrackingSession::query()->create([
            'mission_id' => Mission::factory()->assigned()->create()->id,
            'employee_user_id' => User::factory()->employe()->create()->id,
            'tracking_mode' => 'to_client',
            'is_active' => false,
            'started_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->service()->pushPoint($session, ['lat' => 1.0, 'lng' => 1.0]);
    }

    // ──────────────────────────────────────────────
    // stopTracking / stopActiveForMission
    // ──────────────────────────────────────────────

    public function test_stop_tracking_marks_session_inactive_and_records_last_position(): void
    {
        $session = MissionTrackingSession::query()->create([
            'mission_id' => Mission::factory()->assigned()->create()->id,
            'employee_user_id' => User::factory()->employe()->create()->id,
            'tracking_mode' => 'to_client',
            'is_active' => true,
            'started_at' => now(),
            'last_lat' => 10.0,
            'last_lng' => 20.0,
        ]);

        $stopped = $this->service()->stopTracking($session, 50.0, 4.0);

        $this->assertFalse($stopped->is_active);
        $this->assertNotNull($stopped->ended_at);
        $this->assertEquals(50.0, (float) $stopped->last_lat);
        $this->assertEquals(4.0, (float) $stopped->last_lng);
    }

    public function test_stop_tracking_keeps_existing_position_when_no_coords_given(): void
    {
        $session = MissionTrackingSession::query()->create([
            'mission_id' => Mission::factory()->assigned()->create()->id,
            'employee_user_id' => User::factory()->employe()->create()->id,
            'tracking_mode' => 'to_client',
            'is_active' => true,
            'started_at' => now(),
            'last_lat' => 11.0,
            'last_lng' => 22.0,
        ]);

        $stopped = $this->service()->stopTracking($session);

        $this->assertEquals(11.0, (float) $stopped->last_lat);
        $this->assertEquals(22.0, (float) $stopped->last_lng);
    }

    public function test_stop_active_for_mission_returns_null_when_no_active_session(): void
    {
        $mission = Mission::factory()->assigned()->create();

        $this->assertNull($this->service()->stopActiveForMission($mission));
    }

    public function test_stop_active_for_mission_stops_the_latest_active_session(): void
    {
        $mission = Mission::factory()->assigned()->create();
        $employee = User::factory()->employe()->create();

        $session = MissionTrackingSession::query()->create([
            'mission_id' => $mission->id,
            'employee_user_id' => $employee->id,
            'tracking_mode' => 'to_client',
            'is_active' => true,
            'started_at' => now(),
        ]);

        $result = $this->service()->stopActiveForMission($mission, 12.0, 13.0);

        $this->assertNotNull($result);
        $this->assertSame($session->id, $result->id);
        $this->assertFalse($result->is_active);
        $this->assertEquals(12.0, (float) $result->last_lat);
    }

    // ──────────────────────────────────────────────
    // livePayload
    // ──────────────────────────────────────────────

    public function test_live_payload_computes_distance_and_eta_for_active_session(): void
    {
        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->enRoute()->create([
            'lead_employee_id' => $employee->id,
            'destination_lat' => 50.9000,
            'destination_lng' => 4.4000,
        ]);

        $session = MissionTrackingSession::query()->create([
            'mission_id' => $mission->id,
            'employee_user_id' => $employee->id,
            'tracking_mode' => 'to_client',
            'is_active' => true,
            'started_at' => now(),
            'last_lat' => 50.8503,
            'last_lng' => 4.3517,
        ]);

        MissionTrackingPoint::query()->create([
            'tracking_session_id' => $session->id,
            'recorded_at' => now(),
            'lat' => 50.8503,
            'lng' => 4.3517,
            'speed_kmh' => 30,
            'source' => 'mobile',
        ]);

        $payload = $this->service()->livePayload($mission->fresh());

        $this->assertSame((int) $mission->id, $payload['mission_id']);
        $this->assertSame('en_route', $payload['status']);
        $this->assertTrue($payload['session']['is_active']);
        $this->assertSame($employee->id, $payload['employee']['id']);
        $this->assertEquals(50.8503, $payload['employee_position']['lat']);
        $this->assertEquals(50.9000, $payload['destination']['lat']);
        $this->assertIsInt($payload['distance_meters']);
        $this->assertGreaterThan(0, $payload['distance_meters']);
        $this->assertIsInt($payload['eta_minutes']);
        $this->assertGreaterThan(0, $payload['eta_minutes']);
    }

    public function test_live_payload_returns_null_metrics_without_session(): void
    {
        $mission = Mission::factory()->assigned()->create([
            'destination_lat' => 50.9000,
            'destination_lng' => 4.4000,
        ]);

        $payload = $this->service()->livePayload($mission);

        $this->assertNull($payload['session']['id']);
        $this->assertFalse($payload['session']['is_active']);
        $this->assertNull($payload['employee_position']['lat']);
        $this->assertNull($payload['distance_meters']);
        $this->assertNull($payload['eta_minutes']);
        $this->assertEquals(50.9000, $payload['destination']['lat']);
    }
}
