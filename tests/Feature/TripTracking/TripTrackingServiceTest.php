<?php

namespace Tests\Feature\TripTracking;

use App\Models\Booking;
use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TripTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function createBooking(?User $client = null, ?User $provider = null, array $overrides = []): Booking
    {
        $client ??= User::factory()->client()->create();
        $provider ??= User::factory()->employe()->create();
        return Booking::factory()->create(array_merge([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'en_cours',
        ], $overrides));
    }

    public function test_start_session_creates_enroute_session(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider);

        $session = app(TripTrackingService::class)->startSession($provider, $booking, 48.8566, 2.3522);

        $this->assertInstanceOf(TripTrackingSession::class, $session);
        $this->assertSame(TripTrackingSession::STATUS_ENROUTE, $session->status);
        $this->assertSame(48.8566, $session->start_lat);
        $this->assertSame(2.3522, $session->start_lng);
        $this->assertNotNull($session->started_at);
    }

    public function test_start_session_idempotent_returns_existing(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider);
        $service = app(TripTrackingService::class);

        $s1 = $service->startSession($provider, $booking, 48.8, 2.3);
        $s2 = $service->startSession($provider, $booking, 49.0, 2.5);

        $this->assertSame($s1->id, $s2->id);
        $this->assertSame(48.8, $s1->fresh()->start_lat);   // Premier start préservé
    }

    public function test_record_ping_increments_points_count_and_distance(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider);
        $service = app(TripTrackingService::class);

        $session = $service->startSession($provider, $booking, 48.8566, 2.3522);

        $service->recordPing($session, 48.8566, 2.3522);
        $service->recordPing($session->fresh(), 48.8570, 2.3530);

        $fresh = $session->fresh();
        $this->assertSame(2, (int) $fresh->points_count);
        $this->assertGreaterThan(0, (int) $fresh->total_distance_m);   // Distance > 0 entre les 2 pings
        $this->assertSame(48.8570, $fresh->last_lat);
    }

    public function test_record_ping_dedup_by_client_sequence(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider);
        $service = app(TripTrackingService::class);

        $session = $service->startSession($provider, $booking);

        $p1 = $service->recordPing($session, 48.85, 2.35, null, null, null, 'seq-1');
        $p2 = $service->recordPing($session->fresh(), 48.86, 2.36, null, null, null, 'seq-1');

        $this->assertSame($p1->id, $p2->id);
        $this->assertSame(1, (int) $session->fresh()->points_count);
    }

    public function test_geofence_auto_transitions_to_arrived(): void
    {
        // Booking avec destination connue
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider, overrides: [
            'destination_lat' => 48.8566,
            'destination_lng' => 2.3522,
        ]);
        $service = app(TripTrackingService::class);

        $session = $service->startSession($provider, $booking, 48.9000, 2.4000);

        // Ping loin (3+ km) → reste enroute
        $service->recordPing($session, 48.8900, 2.3800);
        $this->assertSame(TripTrackingSession::STATUS_ENROUTE, $session->fresh()->status);

        // Ping très proche destination (< 150m geofence) → arrived
        $service->recordPing($session->fresh(), 48.8567, 2.3523);
        $this->assertSame(TripTrackingSession::STATUS_ARRIVED, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->arrived_at);
    }

    public function test_mark_in_mission_after_arrived(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider, overrides: [
            'destination_lat' => 48.8566, 'destination_lng' => 2.3522,
        ]);
        $service = app(TripTrackingService::class);

        $session = $service->startSession($provider, $booking);
        $service->recordPing($session, 48.8567, 2.3523);   // → arrived

        $started = $service->markInMission($session->fresh());

        $this->assertSame(TripTrackingSession::STATUS_IN_MISSION, $started->status);
        $this->assertNotNull($started->in_mission_at);
    }

    public function test_end_session_transitions_and_stops_active(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider);
        $service = app(TripTrackingService::class);

        $session = $service->startSession($provider, $booking);
        $ended = $service->endSession($session, 'mission_completed');

        $this->assertSame(TripTrackingSession::STATUS_ENDED, $ended->status);
        $this->assertNotNull($ended->ended_at);
        $this->assertFalse($ended->isActive());
        $this->assertSame('mission_completed', $ended->metadata['end_reason']);
    }

    public function test_record_ping_rejects_ended_session(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider);
        $service = app(TripTrackingService::class);

        $session = $service->startSession($provider, $booking);
        $service->endSession($session);

        $this->expectException(ValidationException::class);
        $service->recordPing($session->fresh(), 48.85, 2.35);
    }

    public function test_active_session_for_booking_returns_only_active(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider);
        $service = app(TripTrackingService::class);

        $s1 = $service->startSession($provider, $booking);
        $service->endSession($s1);
        // Maintenant on peut créer une 2e session
        $s2 = $service->startSession($provider, $booking);

        $active = $service->activeSessionForBooking($booking->id);
        $this->assertNotNull($active);
        $this->assertSame($s2->id, $active->id);
    }

    public function test_eta_seconds_computed_with_speed(): void
    {
        $provider = User::factory()->employe()->create();
        $booking = $this->createBooking(provider: $provider, overrides: [
            'destination_lat' => 48.8566, 'destination_lng' => 2.3522,
        ]);
        $service = app(TripTrackingService::class);

        $session = $service->startSession($provider, $booking);
        // Position ~2km away, speed 10 mps → ETA ≈ 200 sec
        $point = $service->recordPing($session, 48.8400, 2.3700, null, 10.0);

        $this->assertGreaterThan(0, (int) $point->distance_to_dest_m);
        $this->assertGreaterThan(0, (int) $point->eta_seconds);
        $this->assertLessThan(1000, (int) $point->eta_seconds);   // sanity
    }
}
