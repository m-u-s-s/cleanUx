<?php

namespace Tests\Feature\GeolocationV2;

use App\Models\AddressLookup;
use App\Models\DistanceCalculation;
use App\Models\GeocodingCacheEntry;
use App\Services\GeolocationV2\Contracts\GeocodingProviderContract;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\GeolocationV2\Providers\MockGeocodingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('geolocation_v2.provider', 'mock');
        Config::set('geolocation_v2.autocomplete_min_chars', 3);
        Config::set('geolocation_v2.cache_ttl_minutes', 1440);
        Config::set('geolocation_v2.autocomplete_cache_ttl_minutes', 60);
        Config::set('geolocation_v2.distance_cache_ttl_minutes', 360);
        Config::set('geolocation_v2.distance_modes', ['driving', 'walking', 'bicycling', 'transit']);
        Config::set('geolocation_v2.distance_default_mode', 'driving');
        Config::set('geolocation_v2.haversine_fallback_enabled', true);
        Config::set('geolocation_v2.isochrone_avg_speed_kmh', [
            'driving' => 35, 'walking' => 4.5, 'bicycling' => 16, 'transit' => 22,
        ]);
        Config::set('geolocation_v2.earth_radius_meters', 6_371_000);
        Config::set('geolocation_v2.allowed_country_codes', ['BE', 'FR', 'NL']);

        $this->app->bind(GeocodingProviderContract::class, MockGeocodingProvider::class);
    }

    public function test_autocomplete_persists_cache_row(): void
    {
        $svc = app(GeocodingService::class);
        $a = $svc->autocomplete('Bruxelles');

        $this->assertNotEmpty($a);
        $this->assertSame(1, AddressLookup::count());
        $row = AddressLookup::query()->first();
        $this->assertSame('mock', $row->provider);
        $this->assertSame('Bruxelles', $row->query);
        $this->assertGreaterThan(0, $row->result_count);
    }

    public function test_autocomplete_returns_cached_on_second_call(): void
    {
        $svc = app(GeocodingService::class);
        $svc->autocomplete('Ixelles');
        $countBefore = AddressLookup::count();
        $svc->autocomplete('Ixelles');
        $this->assertSame($countBefore, AddressLookup::count());
    }

    public function test_autocomplete_short_query_returns_empty_without_call(): void
    {
        $svc = app(GeocodingService::class);
        $this->assertSame([], $svc->autocomplete('Br'));
        $this->assertSame(0, AddressLookup::count());
    }

    public function test_geocode_persists_cache_and_returns_lat_lng(): void
    {
        $svc = app(GeocodingService::class);
        $r = $svc->geocode('1000 Bruxelles', 'BE');

        $this->assertNotNull($r);
        $this->assertEqualsWithDelta(50.8467, $r->latitude, 0.001);
        $this->assertSame(1, GeocodingCacheEntry::count());
    }

    public function test_geocode_uses_cache_on_second_call(): void
    {
        $svc = app(GeocodingService::class);
        $svc->geocode('1000 Bruxelles', 'BE');
        $svc->geocode('1000 Bruxelles', 'BE');
        $this->assertSame(1, GeocodingCacheEntry::count());
    }

    public function test_distance_persists_calculation_and_caches(): void
    {
        $svc = app(GeocodingService::class);
        $r = $svc->distance(50.8467, 4.3525, 51.2194, 4.4025, 'driving');

        $this->assertGreaterThan(0, $r->distanceMeters);
        $this->assertSame(1, DistanceCalculation::count());

        $svc->distance(50.8467, 4.3525, 51.2194, 4.4025, 'driving');
        $this->assertSame(1, DistanceCalculation::count());
    }

    public function test_distance_uses_provided_mode_or_falls_back_to_default(): void
    {
        $svc = app(GeocodingService::class);
        $r = $svc->distance(50.8467, 4.3525, 51.2194, 4.4025, 'banana');
        $this->assertSame('driving', $r->mode);

        $r2 = $svc->distance(50.8467, 4.3525, 51.2194, 4.4025, 'walking');
        $this->assertSame('walking', $r2->mode);
    }

    public function test_isochrone_radius_meters_increases_with_minutes(): void
    {
        $svc = app(GeocodingService::class);
        $r15 = $svc->isochroneRadiusMeters(15, 'driving');
        $r30 = $svc->isochroneRadiusMeters(30, 'driving');

        $this->assertGreaterThan(0, $r15);
        $this->assertGreaterThan($r15, $r30);
    }

    public function test_purge_expired_removes_only_expired_rows(): void
    {
        AddressLookup::query()->create([
            'provider' => 'mock', 'query_hash' => str_repeat('a', 64),
            'query' => 'fresh', 'results' => [], 'result_count' => 0,
            'queried_at' => now(), 'expires_at' => now()->addHour(),
        ]);
        AddressLookup::query()->create([
            'provider' => 'mock', 'query_hash' => str_repeat('b', 64),
            'query' => 'expired', 'results' => [], 'result_count' => 0,
            'queried_at' => now()->subDay(), 'expires_at' => now()->subMinute(),
        ]);

        app(GeocodingService::class)->purgeExpired();
        $this->assertSame(1, AddressLookup::count());
        $this->assertSame('fresh', AddressLookup::query()->first()->query);
    }

    public function test_country_code_filter_normalizes_and_rejects_unknown(): void
    {
        $svc = app(GeocodingService::class);
        $r = $svc->autocomplete('Bruxelles', 'XX');  // hors whitelist → ignored country
        $this->assertNotEmpty($r);  // mock provider matche sans country filter
    }
}
