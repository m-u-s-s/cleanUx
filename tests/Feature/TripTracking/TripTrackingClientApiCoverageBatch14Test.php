<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripTrackingClientApiCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    protected function makeBookingForClient(User $client, array $overrides = []): Booking
    {
        $provider = User::factory()->employe()->create();

        return Booking::factory()->create(array_merge([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'en_cours',
        ], $overrides));
    }

    protected function makeSessionForBooking(Booking $booking, array $overrides = []): TripTrackingSession
    {
        return TripTrackingSession::query()->create(array_merge([
            'code' => TripTrackingSession::generateCode(),
            'booking_id' => $booking->id,
            'provider_user_id' => $booking->employe_id,
            'status' => TripTrackingSession::STATUS_ENROUTE,
            'destination_lat' => 50.846,
            'destination_lng' => 4.352,
            'geofence_radius_m' => 150,
            'points_count' => 0,
            'total_distance_m' => 0,
            'started_at' => now(),
        ], $overrides));
    }

    public function test_current_for_booking_returns_session_payload(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBookingForClient($client);
        $session = $this->makeSessionForBooking($booking, [
            'last_lat' => 50.840,
            'last_lng' => 4.348,
            'last_speed_mps' => 6.0,
            'current_eta_seconds' => 125,
            'last_ping_at' => now(),
        ]);
        Sanctum::actingAs($client);

        $this->getJson("/api/client/bookings/{$booking->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.code', $session->code)
            ->assertJsonPath('data.status', TripTrackingSession::STATUS_ENROUTE)
            ->assertJsonPath('data.destination.lat', 50.846)
            ->assertJsonPath('data.eta_seconds', 125)
            ->assertJsonPath('data.eta_minutes', 3);
    }

    public function test_current_for_booking_returns_null_when_no_active_session(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBookingForClient($client);
        Sanctum::actingAs($client);

        $this->getJson("/api/client/bookings/{$booking->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_current_for_booking_forbidden_for_other_client(): void
    {
        $owner = User::factory()->client()->create();
        $intruder = User::factory()->client()->create();
        $booking = $this->makeBookingForClient($owner);
        Sanctum::actingAs($intruder);

        $this->getJson("/api/client/bookings/{$booking->id}/tracking")
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_current_for_booking_null_eta_minutes_when_eta_missing(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBookingForClient($client);
        $this->makeSessionForBooking($booking, [
            'current_eta_seconds' => null,
        ]);
        Sanctum::actingAs($client);

        $this->getJson("/api/client/bookings/{$booking->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.eta_minutes', null)
            ->assertJsonPath('data.eta_seconds', null);
    }

    public function test_trail_returns_ordered_points(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBookingForClient($client);
        $session = $this->makeSessionForBooking($booking);

        TripTrackingPoint::query()->create([
            'session_id' => $session->id,
            'lat' => 50.800,
            'lng' => 4.300,
            'eta_seconds' => 200,
            'distance_to_dest_m' => 1200,
            'recorded_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(5),
        ]);
        TripTrackingPoint::query()->create([
            'session_id' => $session->id,
            'lat' => 50.820,
            'lng' => 4.330,
            'eta_seconds' => 90,
            'distance_to_dest_m' => 500,
            'recorded_at' => now()->subMinute(),
            'created_at' => now()->subMinute(),
        ]);
        Sanctum::actingAs($client);

        $this->getJson("/api/client/bookings/{$booking->id}/tracking/trail?limit=5")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.lat', 50.800)
            ->assertJsonPath('data.1.lat', 50.820)
            ->assertJsonPath('data.1.distance_to_dest_m', 500);
    }

    public function test_trail_returns_empty_when_no_active_session(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBookingForClient($client);
        Sanctum::actingAs($client);

        $this->getJson("/api/client/bookings/{$booking->id}/tracking/trail")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_trail_forbidden_for_other_client(): void
    {
        $owner = User::factory()->client()->create();
        $intruder = User::factory()->client()->create();
        $booking = $this->makeBookingForClient($owner);
        $this->makeSessionForBooking($booking);
        Sanctum::actingAs($intruder);

        $this->getJson("/api/client/bookings/{$booking->id}/tracking/trail")
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');
    }
}
