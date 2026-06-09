<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionTrackingSession;
use App\Services\Eta\EtaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EtaServiceCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    private EtaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EtaService::class);
        // Ensure no Google key by default → Haversine fallback path.
        config(['services.google_maps.api_key' => '']);
    }

    // ────────────────────────────────────────────────
    // computeBetween — Haversine fallback
    // ────────────────────────────────────────────────

    public function test_compute_between_uses_haversine_when_no_google_key(): void
    {
        $result = $this->service->computeBetween(50.8503, 4.3517, 50.6326, 5.5797);

        $this->assertSame('haversine', $result['source']);
        $this->assertGreaterThan(80_000, $result['meters']);
        $this->assertGreaterThan(0, $result['seconds']);
        $this->assertNotNull($result['calculated_at']);
    }

    public function test_compute_between_same_point_is_zero(): void
    {
        $result = $this->service->computeBetween(50.0, 4.0, 50.0, 4.0);

        $this->assertSame(0, $result['meters']);
        $this->assertSame(0, $result['seconds']);
        $this->assertSame('haversine', $result['source']);
    }

    // ────────────────────────────────────────────────
    // computeBetween — Google Distance Matrix
    // ────────────────────────────────────────────────

    public function test_compute_between_uses_google_when_key_present(): void
    {
        config(['services.google_maps.api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [[
                    'elements' => [[
                        'status' => 'OK',
                        'duration' => ['value' => 600],
                        'duration_in_traffic' => ['value' => 720],
                        'distance' => ['value' => 5000],
                    ]],
                ]],
            ], 200),
        ]);

        $result = $this->service->computeBetween(50.0, 4.0, 50.1, 4.1);

        $this->assertSame('google', $result['source']);
        $this->assertSame(5000, $result['meters']);
        // duration_in_traffic preferred over duration
        $this->assertSame(720, $result['seconds']);
    }

    public function test_compute_between_google_falls_back_on_http_error(): void
    {
        config(['services.google_maps.api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response('boom', 500),
        ]);

        $result = $this->service->computeBetween(50.0, 4.0, 50.1, 4.1);

        $this->assertSame('haversine', $result['source']);
    }

    public function test_compute_between_google_falls_back_on_non_ok_status(): void
    {
        config(['services.google_maps.api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OVER_QUERY_LIMIT',
            ], 200),
        ]);

        $result = $this->service->computeBetween(50.0, 4.0, 50.1, 4.1);

        $this->assertSame('haversine', $result['source']);
    }

    public function test_compute_between_google_falls_back_on_element_not_ok(): void
    {
        config(['services.google_maps.api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [[
                    'elements' => [[
                        'status' => 'ZERO_RESULTS',
                    ]],
                ]],
            ], 200),
        ]);

        $result = $this->service->computeBetween(50.0, 4.0, 50.1, 4.1);

        $this->assertSame('haversine', $result['source']);
    }

    public function test_compute_between_google_falls_back_when_values_missing(): void
    {
        config(['services.google_maps.api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [[
                    'elements' => [[
                        'status' => 'OK',
                        // no distance / duration values
                    ]],
                ]],
            ], 200),
        ]);

        $result = $this->service->computeBetween(50.0, 4.0, 50.1, 4.1);

        $this->assertSame('haversine', $result['source']);
    }

    // ────────────────────────────────────────────────
    // computeForMission — empty results
    // ────────────────────────────────────────────────

    public function test_compute_for_mission_returns_none_without_active_session(): void
    {
        $mission = Mission::factory()->create();

        $result = $this->service->computeForMission($mission);

        $this->assertSame('none', $result['source']);
        $this->assertNull($result['meters']);
        $this->assertNull($result['calculated_at']);
    }

    public function test_compute_for_mission_returns_none_without_coordinates(): void
    {
        $booking = Booking::factory()->create([
            'destination_lat' => null,
            'destination_lng' => null,
        ]);
        $mission = Mission::factory()->create(['rendez_vous_id' => $booking->id]);

        MissionTrackingSession::factory()->create([
            'mission_id' => $mission->id,
            'is_active' => true,
            'last_lat' => null,
            'last_lng' => null,
        ]);

        $result = $this->service->computeForMission($mission->fresh());

        $this->assertSame('none', $result['source']);
    }

    // ────────────────────────────────────────────────
    // computeForMission — full happy path + cache
    // ────────────────────────────────────────────────

    public function test_compute_for_mission_computes_persists_and_caches(): void
    {
        Cache::flush();

        $booking = Booking::factory()->create([
            'destination_lat' => 50.6326,
            'destination_lng' => 5.5797,
        ]);
        $mission = Mission::factory()->create(['rendez_vous_id' => $booking->id]);

        MissionTrackingSession::factory()->create([
            'mission_id' => $mission->id,
            'is_active' => true,
            'last_lat' => 50.8503,
            'last_lng' => 4.3517,
        ]);

        $first = $this->service->computeForMission($mission->fresh());

        $this->assertSame('haversine', $first['source']);
        $this->assertGreaterThan(0, $first['meters']);

        // Persisted on the mission row
        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'last_eta_source' => 'haversine',
            'last_eta_meters' => $first['meters'],
        ]);

        // Second call (not forced) should hit the cache.
        $second = $this->service->computeForMission($mission->fresh());
        $this->assertSame('cache', $second['source']);
        $this->assertSame($first['meters'], $second['meters']);
    }

    public function test_compute_for_mission_force_bypasses_cache(): void
    {
        Cache::flush();

        $booking = Booking::factory()->create([
            'destination_lat' => 50.6326,
            'destination_lng' => 5.5797,
        ]);
        $mission = Mission::factory()->create(['rendez_vous_id' => $booking->id]);

        MissionTrackingSession::factory()->create([
            'mission_id' => $mission->id,
            'is_active' => true,
            'last_lat' => 50.8503,
            'last_lng' => 4.3517,
        ]);

        $this->service->computeForMission($mission->fresh());
        $forced = $this->service->computeForMission($mission->fresh(), true);

        // Forced recompute returns a fresh (non-cache) source.
        $this->assertSame('haversine', $forced['source']);
    }
}
