<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never hit the network. The legacy GeocodingService calls
        // Nominatim (OpenStreetMap) via the Http facade when bookings/missions
        // are created; stub it with a deterministic Brussels result so the suite
        // is offline-safe and not flaky. Tests that need other HTTP behaviour can
        // still call Http::fake() themselves, which takes precedence.
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '50.8503', 'lon' => '4.3517', 'address' => []],
            ], 200),
        ]);
    }
}
