<?php

namespace Tests\Feature\GeolocationV2;

use App\Services\GeolocationV2\DistanceCalculator;
use App\Services\GeolocationV2\Support\Haversine;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DistanceCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('geolocation_v2.earth_radius_meters', 6_371_000);
    }

    public function test_haversine_returns_zero_for_same_point(): void
    {
        $d = Haversine::distanceMeters(50.8467, 4.3525, 50.8467, 4.3525);
        $this->assertEqualsWithDelta(0.0, $d, 0.001);
    }

    public function test_haversine_bruxelles_to_paris_approx_260km(): void
    {
        $d = Haversine::distanceMeters(50.8467, 4.3525, 48.8566, 2.3522);
        $km = $d / 1000;
        $this->assertGreaterThan(250, $km);
        $this->assertLessThan(280, $km);
    }

    public function test_bearing_north_is_zero(): void
    {
        $b = Haversine::bearingDegrees(0, 0, 1, 0);
        $this->assertEqualsWithDelta(0.0, $b, 0.5);
    }

    public function test_bearing_east_is_90(): void
    {
        $b = Haversine::bearingDegrees(0, 0, 0, 1);
        $this->assertEqualsWithDelta(90.0, $b, 0.5);
    }

    public function test_within_radius_filters_and_sorts_by_distance(): void
    {
        $calc = new DistanceCalculator();
        $origin = ['lat' => 50.8467, 'lng' => 4.3525];   // Bruxelles
        $points = [
            ['id' => 'paris', 'latitude' => 48.8566, 'longitude' => 2.3522],   // 260km
            ['id' => 'ixelles', 'latitude' => 50.8333, 'longitude' => 4.3667], // ~2km
            ['id' => 'liege', 'latitude' => 50.6326, 'longitude' => 5.5797],   // ~90km
        ];
        $within = $calc->withinRadius($origin['lat'], $origin['lng'], $points, 100_000);

        $this->assertCount(2, $within);
        $this->assertSame('ixelles', $within[0]['id']);
        $this->assertSame('liege', $within[1]['id']);
        $this->assertLessThan($within[1]['_distance_meters'], $within[0]['_distance_meters']);
    }

    public function test_within_radius_returns_empty_when_too_far(): void
    {
        $calc = new DistanceCalculator();
        $r = $calc->withinRadius(50.8467, 4.3525, [
            ['latitude' => 35.0, 'longitude' => 139.0],  // Tokyo
        ], 1_000_000);
        $this->assertEmpty($r);
    }
}
