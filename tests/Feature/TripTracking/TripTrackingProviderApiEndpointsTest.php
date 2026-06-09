<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripTrackingProviderApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBooking(User $provider, array $overrides = []): Booking
    {
        $client = User::factory()->client()->create();

        return Booking::factory()->create(array_merge([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'en_cours',
        ], $overrides));
    }

    protected function makeSession(User $provider, array $overrides = []): TripTrackingSession
    {
        $booking = $this->makeBooking($provider, array_filter([
            'destination_lat' => $overrides['destination_lat'] ?? null,
            'destination_lng' => $overrides['destination_lng'] ?? null,
        ], fn ($v) => $v !== null));

        return TripTrackingSession::query()->create(array_merge([
            'code' => TripTrackingSession::generateCode(),
            'booking_id' => $booking->id,
            'provider_user_id' => $provider->id,
            'status' => TripTrackingSession::STATUS_ENROUTE,
            'destination_lat' => 50.846,
            'destination_lng' => 4.352,
            'geofence_radius_m' => 150,
            'points_count' => 0,
            'total_distance_m' => 0,
            'started_at' => now(),
        ], $overrides));
    }

    public function test_start_creates_enroute_session_for_assigned_provider(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->makeBooking($provider, [
            'destination_lat' => 50.846,
            'destination_lng' => 4.352,
        ]);
        Sanctum::actingAs($provider);

        $response = $this->postJson("/api/provider/bookings/{$booking->id}/tracking/start", [
            'start_lat' => 50.843,
            'start_lng' => 4.348,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.status', TripTrackingSession::STATUS_ENROUTE)
            ->assertJsonPath('data.points_count', 0);

        $this->assertDatabaseHas('trip_tracking_sessions', [
            'booking_id' => $booking->id,
            'provider_user_id' => $provider->id,
            'status' => TripTrackingSession::STATUS_ENROUTE,
        ]);
    }

    public function test_start_rejects_provider_not_assigned_to_booking(): void
    {
        $owner = User::factory()->employe()->create();
        $intruder = User::factory()->employe()->create();
        $booking = $this->makeBooking($owner);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/provider/bookings/{$booking->id}/tracking/start")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Not assigned to this booking.');
    }

    public function test_start_validates_lat_range(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->makeBooking($provider);
        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/bookings/{$booking->id}/tracking/start", [
            'start_lat' => 200,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('start_lat');
    }

    public function test_ping_records_point_and_returns_session_status(): void
    {
        $provider = User::factory()->employe()->create();
        $session = $this->makeSession($provider);
        Sanctum::actingAs($provider);

        $response = $this->postJson("/api/provider/tracking/{$session->id}/ping", [
            'lat' => 50.800,
            'lng' => 4.300,
            'accuracy_m' => 8.5,
            'speed_mps' => 5.2,
            'heading_deg' => 45.0,
            'sequence' => 'seq-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.session_status', TripTrackingSession::STATUS_ENROUTE)
            ->assertJsonStructure(['data' => ['point_id', 'distance_to_dest_m', 'eta_seconds', 'session_status']]);

        $this->assertSame(1, (int) $session->fresh()->points_count);
    }

    public function test_ping_auto_transitions_to_arrived_within_geofence(): void
    {
        $provider = User::factory()->employe()->create();
        $session = $this->makeSession($provider);
        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/tracking/{$session->id}/ping", [
            'lat' => 50.8461,
            'lng' => 4.3521,
        ])->assertCreated()
            ->assertJsonPath('data.session_status', TripTrackingSession::STATUS_ARRIVED);
    }

    public function test_ping_validation_fails_when_lat_missing(): void
    {
        $provider = User::factory()->employe()->create();
        $session = $this->makeSession($provider);
        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/tracking/{$session->id}/ping", [
            'lng' => 4.300,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('lat');
    }

    public function test_ping_rejects_session_owned_by_another_provider(): void
    {
        $owner = User::factory()->employe()->create();
        $intruder = User::factory()->employe()->create();
        $session = $this->makeSession($owner);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/provider/tracking/{$session->id}/ping", [
            'lat' => 50.8,
            'lng' => 4.3,
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Not your session.');
    }

    public function test_ping_returns_validation_failed_for_inactive_session(): void
    {
        $provider = User::factory()->employe()->create();
        $session = $this->makeSession($provider, [
            'status' => TripTrackingSession::STATUS_ENDED,
            'ended_at' => now(),
        ]);
        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/tracking/{$session->id}/ping", [
            'lat' => 50.8,
            'lng' => 4.3,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'validation_failed')
            ->assertJsonPath('errors.session.0', 'Session non active.');
    }

    public function test_mark_in_mission_transitions_arrived_session(): void
    {
        $provider = User::factory()->employe()->create();
        $session = $this->makeSession($provider, [
            'status' => TripTrackingSession::STATUS_ARRIVED,
            'arrived_at' => now(),
        ]);
        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/tracking/{$session->id}/in-mission")
            ->assertOk()
            ->assertJsonPath('data.status', TripTrackingSession::STATUS_IN_MISSION);

        $this->assertNotNull($session->fresh()->in_mission_at);
    }

    public function test_mark_in_mission_rejects_ended_session_with_validation_failed(): void
    {
        $provider = User::factory()->employe()->create();
        $session = $this->makeSession($provider, [
            'status' => TripTrackingSession::STATUS_ENDED,
            'ended_at' => now(),
        ]);
        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/tracking/{$session->id}/in-mission")
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_failed')
            ->assertJsonPath('errors.status.0', 'Transition impossible vers in_mission.');
    }

    public function test_mark_in_mission_rejects_session_of_another_provider(): void
    {
        $owner = User::factory()->employe()->create();
        $intruder = User::factory()->employe()->create();
        $session = $this->makeSession($owner, [
            'status' => TripTrackingSession::STATUS_ARRIVED,
            'arrived_at' => now(),
        ]);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/provider/tracking/{$session->id}/in-mission")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Not your session.');
    }

    public function test_end_session_sets_ended_status_and_reason(): void
    {
        $provider = User::factory()->employe()->create();
        $session = $this->makeSession($provider, [
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'in_mission_at' => now(),
        ]);
        Sanctum::actingAs($provider);

        $this->postJson("/api/provider/tracking/{$session->id}/end", [
            'reason' => 'Mission terminee',
        ])->assertOk()
            ->assertJsonPath('data.status', TripTrackingSession::STATUS_ENDED);

        $fresh = $session->fresh();
        $this->assertNotNull($fresh->ended_at);
        $this->assertSame('Mission terminee', $fresh->metadata['end_reason']);
    }

    public function test_end_session_rejects_session_of_another_provider(): void
    {
        $owner = User::factory()->employe()->create();
        $intruder = User::factory()->employe()->create();
        $session = $this->makeSession($owner, [
            'status' => TripTrackingSession::STATUS_IN_MISSION,
            'in_mission_at' => now(),
        ]);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/provider/tracking/{$session->id}/end")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Not your session.');
    }
}
