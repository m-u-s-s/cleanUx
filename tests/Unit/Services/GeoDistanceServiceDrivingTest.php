<?php

namespace Tests\Unit\Services;

use App\Services\Geo\GeoDistanceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * 7.6 — GeoDistanceService::drivingDistanceKm()
 */
class GeoDistanceServiceDrivingTest extends TestCase
{
    private GeoDistanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeoDistanceService;
    }

    public function test_returns_null_when_api_key_absent(): void
    {
        config(['services.google.maps_key' => '']);
        config(['services.google.maps_api_key' => '']);

        $result = $this->service->drivingDistanceKm(50.85, 4.35, 48.86, 2.35);

        $this->assertNull($result);
    }

    public function test_returns_array_with_correct_keys_on_success(): void
    {
        config(['services.google.maps_key' => 'fake-api-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [[
                    'elements' => [[
                        'status' => 'OK',
                        'distance' => ['value' => 287000, 'text' => '287 km'],
                        'duration' => ['value' => 9600,   'text' => '2h 40min'],
                    ]],
                ]],
            ]),
        ]);

        $result = $this->service->drivingDistanceKm(50.85, 4.35, 48.86, 2.35);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('distance_km', $result);
        $this->assertArrayHasKey('duration_minutes', $result);
        $this->assertEqualsWithDelta(287.0, $result['distance_km'], 0.1);
        $this->assertSame(160, $result['duration_minutes']);
    }

    public function test_returns_null_on_http_error(): void
    {
        config(['services.google.maps_key' => 'fake-api-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response('', 500),
        ]);

        $result = $this->service->drivingDistanceKm(50.85, 4.35, 48.86, 2.35);

        $this->assertNull($result);
    }

    public function test_returns_null_when_element_status_not_ok(): void
    {
        config(['services.google.maps_key' => 'fake-api-key']);

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'rows' => [[
                    'elements' => [[
                        'status' => 'ZERO_RESULTS',
                    ]],
                ]],
            ]),
        ]);

        $result = $this->service->drivingDistanceKm(50.85, 4.35, 48.86, 2.35);

        $this->assertNull($result);
    }

    public function test_returns_null_on_connection_exception(): void
    {
        config(['services.google.maps_key' => 'fake-api-key']);

        Http::fake([
            'maps.googleapis.com/*' => function () {
                throw new ConnectionException('timeout');
            },
        ]);

        $result = $this->service->drivingDistanceKm(50.85, 4.35, 48.86, 2.35);

        $this->assertNull($result);
    }
}
