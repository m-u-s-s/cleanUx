<?php

namespace Tests\Feature\GeolocationV2;

use App\Services\GeolocationV2\Providers\MockGeocodingProvider;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MockGeocodingProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('geolocation_v2.isochrone_avg_speed_kmh', [
            'driving' => 35, 'walking' => 4.5, 'bicycling' => 16, 'transit' => 22,
        ]);
        Config::set('geolocation_v2.earth_radius_meters', 6_371_000);
    }

    public function test_autocomplete_matches_postal_or_locality_prefix(): void
    {
        $p = new MockGeocodingProvider();
        $results = $p->autocomplete('Brux');

        $this->assertNotEmpty($results);
        $this->assertSame('Bruxelles', $results[0]->mainText);
        $this->assertSame('BE', $results[0]->countryCode);
        $this->assertSame('1000', $results[0]->postalCode);
    }

    public function test_autocomplete_country_filter_excludes_others(): void
    {
        $p = new MockGeocodingProvider();
        $resultsFr = $p->autocomplete('Paris', 'FR');
        $resultsBe = $p->autocomplete('Paris', 'BE');

        $this->assertNotEmpty($resultsFr);
        $this->assertSame('FR', $resultsFr[0]->countryCode);
        $this->assertEmpty($resultsBe);
    }

    public function test_geocode_returns_lat_lng_for_known_postal(): void
    {
        $p = new MockGeocodingProvider();
        $r = $p->geocode('1050 Ixelles');

        $this->assertNotNull($r);
        $this->assertEqualsWithDelta(50.8333, $r->latitude, 0.001);
        $this->assertEqualsWithDelta(4.3667, $r->longitude, 0.001);
        $this->assertSame('1050', $r->postalCode);
        $this->assertSame('BE', $r->countryCode);
    }

    public function test_reverse_geocode_returns_closest_entry(): void
    {
        $p = new MockGeocodingProvider();
        // près d'Anvers
        $r = $p->reverseGeocode(51.22, 4.40);

        $this->assertNotNull($r);
        $this->assertSame('Anvers', $r->locality);
        $this->assertSame('2000', $r->postalCode);
    }

    public function test_distance_returns_haversine_meters_and_duration(): void
    {
        $p = new MockGeocodingProvider();
        // Bruxelles → Anvers (≈ 40km à vol d'oiseau)
        $r = $p->distance(50.8467, 4.3525, 51.2194, 4.4025, 'driving');

        $this->assertNotNull($r);
        $this->assertGreaterThan(35_000, $r->distanceMeters);
        $this->assertLessThan(50_000, $r->distanceMeters);
        $this->assertGreaterThan(0, $r->durationSeconds);
    }
}
