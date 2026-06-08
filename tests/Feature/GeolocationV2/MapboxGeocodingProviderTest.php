<?php

namespace Tests\Feature\GeolocationV2;

use App\Services\GeolocationV2\AddressSuggestion;
use App\Services\GeolocationV2\DistanceResult;
use App\Services\GeolocationV2\GeocodingResult;
use App\Services\GeolocationV2\Providers\MapboxGeocodingProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapboxGeocodingProviderTest extends TestCase
{
    private MapboxGeocodingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('geolocation_v2.providers.mapbox.access_token', 'test-token');
        Config::set('geolocation_v2.providers.mapbox.geocode_endpoint', 'https://api.mapbox.com/geocoding/v5/mapbox.places');
        Config::set('geolocation_v2.providers.mapbox.directions_endpoint', 'https://api.mapbox.com/directions-matrix/v1/mapbox');
        Config::set('geolocation_v2.timeout_seconds', 8);

        $this->provider = new MapboxGeocodingProvider;
    }

    private function geocodeFeature(): array
    {
        return [
            'id' => 'address.1',
            'place_name' => 'Rue de la Loi, 1000 Brussels, Belgium',
            'text' => 'Rue de la Loi',
            'geometry' => ['coordinates' => [4.3676, 50.8467]],
            'context' => [
                ['id' => 'postcode.123', 'text' => '1000'],
                ['id' => 'place.456', 'text' => 'Brussels'],
                ['id' => 'country.789', 'short_code' => 'be', 'text' => 'Belgium'],
            ],
        ];
    }

    public function test_name_is_mapbox(): void
    {
        $this->assertSame('mapbox', $this->provider->name());
    }

    public function test_autocomplete_returns_empty_without_token(): void
    {
        Config::set('geolocation_v2.providers.mapbox.access_token', null);
        Http::fake();

        $this->assertSame([], $this->provider->autocomplete('Brussels'));
        Http::assertNothingSent();
    }

    public function test_autocomplete_parses_features(): void
    {
        Http::fake([
            'api.mapbox.com/geocoding/*' => Http::response(['features' => [$this->geocodeFeature()]], 200),
        ]);

        $results = $this->provider->autocomplete('Rue de la Loi', 'BE', 5);

        $this->assertCount(1, $results);
        $first = $results[0];
        $this->assertInstanceOf(AddressSuggestion::class, $first);
        $this->assertSame('Rue de la Loi, 1000 Brussels, Belgium', $first->description);
        $this->assertSame('address.1', $first->placeId);
        $this->assertSame('BE', $first->countryCode);
        $this->assertSame('Rue de la Loi', $first->mainText);
        $this->assertSame('1000', $first->postalCode);
        $this->assertEqualsWithDelta(50.8467, $first->latitude, 0.0001);
        $this->assertEqualsWithDelta(4.3676, $first->longitude, 0.0001);
        $this->assertSame('mapbox', $first->provider);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Rue%20de%20la%20Loi.json')
                && str_contains($request->url(), 'autocomplete=true')
                && str_contains($request->url(), 'country=be');
        });
    }

    public function test_autocomplete_returns_empty_on_failed_response(): void
    {
        Http::fake([
            'api.mapbox.com/geocoding/*' => Http::response('error', 500),
        ]);

        $this->assertSame([], $this->provider->autocomplete('Brussels'));
    }

    public function test_autocomplete_swallows_exception_and_returns_empty(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('network down');
        });

        $this->assertSame([], $this->provider->autocomplete('Brussels'));
    }

    public function test_geocode_returns_result_from_first_suggestion(): void
    {
        Http::fake([
            'api.mapbox.com/geocoding/*' => Http::response(['features' => [$this->geocodeFeature()]], 200),
        ]);

        $result = $this->provider->geocode('Rue de la Loi', 'BE');

        $this->assertInstanceOf(GeocodingResult::class, $result);
        $this->assertEqualsWithDelta(50.8467, $result->latitude, 0.0001);
        $this->assertEqualsWithDelta(4.3676, $result->longitude, 0.0001);
        $this->assertSame('Rue de la Loi, 1000 Brussels, Belgium', $result->formattedAddress);
        $this->assertSame('address.1', $result->placeId);
        $this->assertSame('BE', $result->countryCode);
        $this->assertSame('1000', $result->postalCode);
        $this->assertSame('Rue de la Loi', $result->locality);
        $this->assertSame('mapbox', $result->provider);
    }

    public function test_geocode_returns_null_when_no_features(): void
    {
        Http::fake([
            'api.mapbox.com/geocoding/*' => Http::response(['features' => []], 200),
        ]);

        $this->assertNull($this->provider->geocode('Nowhere', 'BE'));
    }

    public function test_reverse_geocode_returns_null_without_token(): void
    {
        Config::set('geolocation_v2.providers.mapbox.access_token', null);
        Http::fake();

        $this->assertNull($this->provider->reverseGeocode(50.8467, 4.3676));
        Http::assertNothingSent();
    }

    public function test_reverse_geocode_parses_first_feature(): void
    {
        Http::fake([
            'api.mapbox.com/geocoding/*' => Http::response(['features' => [$this->geocodeFeature()]], 200),
        ]);

        $result = $this->provider->reverseGeocode(50.8467, 4.3676);

        $this->assertInstanceOf(GeocodingResult::class, $result);
        $this->assertEqualsWithDelta(50.8467, $result->latitude, 0.0001);
        $this->assertEqualsWithDelta(4.3676, $result->longitude, 0.0001);
        $this->assertSame('BE', $result->countryCode);
        $this->assertSame('1000', $result->postalCode);
        $this->assertSame('Brussels', $result->locality);
        $this->assertSame('mapbox', $result->provider);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '4.3676,50.8467.json');
        });
    }

    public function test_reverse_geocode_returns_null_on_failed_response(): void
    {
        Http::fake([
            'api.mapbox.com/geocoding/*' => Http::response('boom', 502),
        ]);

        $this->assertNull($this->provider->reverseGeocode(50.0, 4.0));
    }

    public function test_reverse_geocode_returns_null_when_no_features(): void
    {
        Http::fake([
            'api.mapbox.com/geocoding/*' => Http::response(['features' => []], 200),
        ]);

        $this->assertNull($this->provider->reverseGeocode(50.0, 4.0));
    }

    public function test_distance_returns_null_without_token(): void
    {
        Config::set('geolocation_v2.providers.mapbox.access_token', null);
        Http::fake();

        $this->assertNull($this->provider->distance(50.0, 4.0, 51.0, 4.5));
        Http::assertNothingSent();
    }

    public function test_distance_parses_matrix_response(): void
    {
        Http::fake([
            'api.mapbox.com/directions-matrix/*' => Http::response([
                'distances' => [[0, 12345.6]],
                'durations' => [[0, 678.9]],
            ], 200),
        ]);

        $result = $this->provider->distance(50.8467, 4.3676, 51.2194, 4.4025, 'driving');

        $this->assertInstanceOf(DistanceResult::class, $result);
        $this->assertSame(12346, $result->distanceMeters);
        $this->assertSame(679, $result->durationSeconds);
        $this->assertSame('driving', $result->mode);
        $this->assertSame('mapbox', $result->provider);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/driving/')
                && str_contains($request->url(), 'annotations=distance%2Cduration');
        });
    }

    public function test_distance_maps_walking_and_bicycling_profiles(): void
    {
        Http::fake([
            'api.mapbox.com/directions-matrix/*' => Http::response([
                'distances' => [[0, 500.0]],
                'durations' => [[0, 60.0]],
            ], 200),
        ]);

        $walk = $this->provider->distance(50.0, 4.0, 50.1, 4.1, 'walking');
        $this->assertSame('walking', $walk->mode);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/walking/'));

        $bike = $this->provider->distance(50.0, 4.0, 50.1, 4.1, 'bicycling');
        $this->assertSame('bicycling', $bike->mode);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/cycling/'));
    }

    public function test_distance_returns_null_when_meters_not_positive(): void
    {
        Http::fake([
            'api.mapbox.com/directions-matrix/*' => Http::response([
                'distances' => [[0, 0]],
                'durations' => [[0, 0]],
            ], 200),
        ]);

        $this->assertNull($this->provider->distance(50.0, 4.0, 50.0, 4.0, 'driving'));
    }

    public function test_distance_returns_null_on_failed_response(): void
    {
        Http::fake([
            'api.mapbox.com/directions-matrix/*' => Http::response('nope', 500),
        ]);

        $this->assertNull($this->provider->distance(50.0, 4.0, 51.0, 4.5, 'driving'));
    }
}
