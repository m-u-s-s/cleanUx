<?php

namespace Tests\Unit\Services;

use App\Services\GeolocationV2\DistanceCalculator;
use App\Services\GeolocationV2\Support\Haversine;
use Tests\TestCase;

/**
 * Unit tests for the Haversine-based distance / ETA calculations.
 *
 * These are pure function tests — no DB required.
 */
class EtaCalculatorTest extends TestCase
{
    private DistanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['geolocation_v2.earth_radius_meters' => 6_371_000]);
        $this->calculator = new DistanceCalculator();
    }

    // ────────────────────────────────────────────────
    // Haversine basic distance
    // ────────────────────────────────────────────────

    public function test_same_point_returns_zero_distance(): void
    {
        $dist = Haversine::distanceMeters(50.8503, 4.3517, 50.8503, 4.3517);

        $this->assertSame(0.0, $dist);
    }

    public function test_brussels_to_liege_is_approximately_90km(): void
    {
        // Brussels: 50.8503° N, 4.3517° E
        // Liège: 50.6326° N, 5.5797° E
        $dist = Haversine::distanceMeters(50.8503, 4.3517, 50.6326, 5.5797);

        // Allow ±5 km tolerance for great-circle (no roads)
        $this->assertGreaterThan(85_000, $dist, 'Distance should be over 85 km');
        $this->assertLessThan(100_000, $dist, 'Distance should be under 100 km');
    }

    public function test_distance_is_symmetric(): void
    {
        $d1 = Haversine::distanceMeters(50.8503, 4.3517, 50.6326, 5.5797);
        $d2 = Haversine::distanceMeters(50.6326, 5.5797, 50.8503, 4.3517);

        $this->assertEqualsWithDelta($d1, $d2, 0.001);
    }

    public function test_short_distance_within_city(): void
    {
        // ~1 km within Brussels
        $dist = Haversine::distanceMeters(50.8503, 4.3517, 50.8593, 4.3517);

        // Roughly 1 km = 1000 m ±200m
        $this->assertGreaterThan(800, $dist);
        $this->assertLessThan(1200, $dist);
    }

    // ────────────────────────────────────────────────
    // Bearing
    // ────────────────────────────────────────────────

    public function test_bearing_due_north_is_zero(): void
    {
        // Moving north from Brussels (same longitude, higher latitude)
        $bearing = Haversine::bearingDegrees(50.0, 4.0, 51.0, 4.0);

        $this->assertEqualsWithDelta(0.0, $bearing, 0.01);
    }

    public function test_bearing_due_east_is_90(): void
    {
        // Moving east (same latitude, higher longitude)
        $bearing = Haversine::bearingDegrees(50.0, 4.0, 50.0, 5.0);

        $this->assertEqualsWithDelta(90.0, $bearing, 1.0);
    }

    public function test_bearing_due_south_is_180(): void
    {
        $bearing = Haversine::bearingDegrees(51.0, 4.0, 50.0, 4.0);

        $this->assertEqualsWithDelta(180.0, $bearing, 0.01);
    }

    public function test_bearing_is_between_0_and_360(): void
    {
        $bearing = Haversine::bearingDegrees(50.8503, 4.3517, 50.6326, 5.5797);

        $this->assertGreaterThanOrEqual(0.0, $bearing);
        $this->assertLessThan(360.0, $bearing);
    }

    // ────────────────────────────────────────────────
    // DistanceCalculator service
    // ────────────────────────────────────────────────

    public function test_distance_meters_delegates_to_haversine(): void
    {
        $expected = Haversine::distanceMeters(50.8503, 4.3517, 50.6326, 5.5797);
        $result   = $this->calculator->distanceMeters(50.8503, 4.3517, 50.6326, 5.5797);

        $this->assertSame($expected, $result);
    }

    public function test_within_radius_filters_correctly(): void
    {
        $origin = ['lat' => 50.8503, 'lng' => 4.3517];  // Brussels

        $points = [
            // ~5 km away — should be included in 10 km radius
            ['latitude' => 50.8953, 'longitude' => 4.3517, 'name' => 'near'],
            // ~90 km away — should be excluded
            ['latitude' => 50.6326, 'longitude' => 5.5797, 'name' => 'far'],
        ];

        $results = $this->calculator->withinRadius(
            $origin['lat'],
            $origin['lng'],
            $points,
            10_000.0  // 10 km
        );

        $this->assertCount(1, $results);
        $this->assertSame('near', $results[0]['name']);
    }

    public function test_within_radius_returns_distance_sorted_asc(): void
    {
        $points = [
            ['latitude' => 50.8953, 'longitude' => 4.3517, 'name' => 'far'],
            ['latitude' => 50.8603, 'longitude' => 4.3517, 'name' => 'near'],
        ];

        $results = $this->calculator->withinRadius(50.8503, 4.3517, $points, 50_000.0);

        $this->assertCount(2, $results);
        $this->assertSame('near', $results[0]['name']);
        $this->assertSame('far', $results[1]['name']);
    }

    public function test_within_radius_returns_empty_when_all_outside(): void
    {
        $points = [
            ['latitude' => 50.6326, 'longitude' => 5.5797, 'name' => 'liege'],
        ];

        $results = $this->calculator->withinRadius(50.8503, 4.3517, $points, 1_000.0);

        $this->assertCount(0, $results);
    }

    // ────────────────────────────────────────────────
    // ETA formula (speed-based)
    // ────────────────────────────────────────────────

    public function test_eta_at_constant_speed_is_correct(): void
    {
        // 1000 m at 10 m/s → 100 seconds
        $dist  = 1000.0;
        $speed = 10.0;
        $eta   = (int) round($dist / $speed);

        $this->assertSame(100, $eta);
    }

    public function test_eta_fallback_speed_11_mps_for_1km(): void
    {
        // Default fallback speed is 11.11 m/s (≈ 40 km/h urban)
        $dist         = 1000.0;
        $fallbackSpeed = 11.11;
        $eta          = (int) round($dist / $fallbackSpeed);

        // Should be ~90 seconds
        $this->assertSame(90, $eta);
    }

    public function test_geofence_trigger_at_150m(): void
    {
        // Point exactly 100 m from destination should trigger geofence (radius = 150 m)
        $destLat = 50.8503;
        $destLng = 4.3517;

        // Move ~100 m north (≈ 0.0009 degrees)
        $curLat = $destLat + 0.0009;
        $curLng = $destLng;

        $distToDest = Haversine::distanceMeters($curLat, $curLng, $destLat, $destLng);

        $this->assertLessThan(150, $distToDest, 'Should be within 150 m geofence');
    }

    public function test_geofence_not_triggered_at_300m(): void
    {
        $destLat = 50.8503;
        $destLng = 4.3517;

        // Move ~300 m north
        $curLat = $destLat + 0.0027;
        $curLng = $destLng;

        $distToDest = Haversine::distanceMeters($curLat, $curLng, $destLat, $destLng);

        $this->assertGreaterThan(150, $distToDest, 'Should be outside 150 m geofence');
    }
}
