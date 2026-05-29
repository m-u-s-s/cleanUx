<?php

namespace Tests\Unit\Services;

use App\Services\Geo\GeoDistanceService;
use Tests\TestCase;

class GeoDistanceServiceTest extends TestCase
{
    private GeoDistanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeoDistanceService;
    }

    public function test_same_point_returns_zero_distance(): void
    {
        $dist = $this->service->haversineKm(50.85, 4.35, 50.85, 4.35);

        $this->assertSame(0.0, $dist);
    }

    public function test_brussels_to_paris_is_approximately_264_km(): void
    {
        $dist = $this->service->haversineKm(50.8503, 4.3517, 48.8566, 2.3522);

        $this->assertEqualsWithDelta(264.0, $dist, 5.0);
    }

    public function test_brussels_to_amsterdam_is_approximately_173_km(): void
    {
        $dist = $this->service->haversineKm(50.8503, 4.3517, 52.3676, 4.9041);

        $this->assertEqualsWithDelta(173.0, $dist, 5.0);
    }

    public function test_distance_is_symmetric(): void
    {
        $ab = $this->service->haversineKm(50.8503, 4.3517, 48.8566, 2.3522);
        $ba = $this->service->haversineKm(48.8566, 2.3522, 50.8503, 4.3517);

        $this->assertSame($ab, $ba);
    }

    public function test_very_short_distance_returns_small_positive_value(): void
    {
        $dist = $this->service->haversineKm(50.8503, 4.3517, 50.8512, 4.3520);

        $this->assertGreaterThan(0.0, $dist);
        $this->assertLessThan(1.0, $dist);
    }
}
