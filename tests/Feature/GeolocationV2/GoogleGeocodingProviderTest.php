<?php

namespace Tests\Feature\GeolocationV2;

use App\Services\GeolocationV2\Providers\GoogleGeocodingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleGeocodingProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure a known endpoint config; default api_key null unless overridden per-test.
        Config::set('geolocation_v2.providers.google', [
            'api_key' => null,
            'places_endpoint' => 'https://maps.googleapis.com/maps/api/place/autocomplete/json',
            'geocode_endpoint' => 'https://maps.googleapis.com/maps/api/geocode/json',
            'distance_endpoint' => 'https://maps.googleapis.com/maps/api/distancematrix/json',
            'language' => 'fr',
        ]);
        Config::set('geolocation_v2.timeout_seconds', 8);
    }

    private function withApiKey(): void
    {
        Config::set('geolocation_v2.providers.google.api_key', 'test-key');
    }

    public function test_name_is_google(): void
    {
        $this->assertSame('google', (new GoogleGeocodingProvider)->name());
    }

    // ---- autocomplete ----------------------------------------------------

    public function test_autocomplete_returns_empty_without_api_key(): void
    {
        Http::fake();
        $results = (new GoogleGeocodingProvider)->autocomplete('Brussels', 'BE');

        $this->assertSame([], $results);
        Http::assertNothingSent();
    }

    public function test_autocomplete_maps_predictions_and_respects_limit(): void
    {
        $this->withApiKey();
        Http::fake([
            '*place/autocomplete*' => Http::response([
                'predictions' => [
                    [
                        'description' => 'Rue de la Loi, Brussels',
                        'place_id' => 'pid-1',
                        'structured_formatting' => [
                            'main_text' => 'Rue de la Loi',
                            'secondary_text' => 'Brussels, Belgium',
                        ],
                    ],
                    [
                        'description' => 'Avenue Louise, Brussels',
                        'place_id' => 'pid-2',
                    ],
                    [
                        'description' => 'Third result',
                        'place_id' => 'pid-3',
                    ],
                ],
            ], 200),
        ]);

        $results = (new GoogleGeocodingProvider)->autocomplete('Brus', 'BE', 2);

        $this->assertCount(2, $results);
        $this->assertSame('Rue de la Loi, Brussels', $results[0]->description);
        $this->assertSame('pid-1', $results[0]->placeId);
        $this->assertSame('BE', $results[0]->countryCode);
        $this->assertSame('Rue de la Loi', $results[0]->mainText);
        $this->assertSame('Brussels, Belgium', $results[0]->secondaryText);
        $this->assertSame('google', $results[0]->provider);
        // second prediction had no structured_formatting -> null mainText
        $this->assertNull($results[1]->mainText);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'place/autocomplete')
                && $request['key'] === 'test-key'
                && $request['components'] === 'country:be';
        });
    }

    public function test_autocomplete_returns_empty_on_unsuccessful_response(): void
    {
        $this->withApiKey();
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->assertSame([], (new GoogleGeocodingProvider)->autocomplete('Brus'));
    }

    public function test_autocomplete_returns_empty_on_exception(): void
    {
        $this->withApiKey();
        Http::fake(['*' => fn () => throw new \RuntimeException('boom')]);

        $this->assertSame([], (new GoogleGeocodingProvider)->autocomplete('Brus'));
    }

    // ---- geocode ---------------------------------------------------------

    public function test_geocode_returns_null_without_api_key(): void
    {
        Http::fake();
        $this->assertNull((new GoogleGeocodingProvider)->geocode('Rue de la Loi 16, Brussels'));
        Http::assertNothingSent();
    }

    public function test_geocode_maps_first_result(): void
    {
        $this->withApiKey();
        Http::fake([
            '*geocode*' => Http::response([
                'results' => [
                    [
                        'formatted_address' => 'Rue de la Loi 16, 1000 Brussels, Belgium',
                        'place_id' => 'geo-pid',
                        'geometry' => ['location' => ['lat' => 50.8467, 'lng' => 4.3525]],
                        'address_components' => [
                            ['types' => ['country'], 'short_name' => 'BE', 'long_name' => 'Belgium'],
                            ['types' => ['postal_code'], 'long_name' => '1000'],
                            ['types' => ['locality'], 'long_name' => 'Brussels'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $r = (new GoogleGeocodingProvider)->geocode('Rue de la Loi 16', 'BE');

        $this->assertNotNull($r);
        $this->assertEqualsWithDelta(50.8467, $r->latitude, 0.0001);
        $this->assertEqualsWithDelta(4.3525, $r->longitude, 0.0001);
        $this->assertSame('Rue de la Loi 16, 1000 Brussels, Belgium', $r->formattedAddress);
        $this->assertSame('geo-pid', $r->placeId);
        $this->assertSame('BE', $r->countryCode);
        $this->assertSame('1000', $r->postalCode);
        $this->assertSame('Brussels', $r->locality);
        $this->assertSame('google', $r->provider);
    }

    public function test_geocode_returns_null_when_no_results(): void
    {
        $this->withApiKey();
        Http::fake(['*geocode*' => Http::response(['results' => []], 200)]);

        $this->assertNull((new GoogleGeocodingProvider)->geocode('Nowhere'));
    }

    public function test_geocode_returns_null_on_unsuccessful_response(): void
    {
        $this->withApiKey();
        Http::fake(['*' => Http::response('err', 503)]);

        $this->assertNull((new GoogleGeocodingProvider)->geocode('Somewhere'));
    }

    public function test_geocode_returns_null_on_exception(): void
    {
        $this->withApiKey();
        Http::fake(['*' => fn () => throw new \RuntimeException('boom')]);

        $this->assertNull((new GoogleGeocodingProvider)->geocode('Somewhere'));
    }

    // ---- reverseGeocode --------------------------------------------------

    public function test_reverse_geocode_returns_null_without_api_key(): void
    {
        Http::fake();
        $this->assertNull((new GoogleGeocodingProvider)->reverseGeocode(50.8, 4.3));
        Http::assertNothingSent();
    }

    public function test_reverse_geocode_maps_result_and_falls_back_to_postal_town(): void
    {
        $this->withApiKey();
        Http::fake([
            '*geocode*' => Http::response([
                'results' => [
                    [
                        'formatted_address' => 'Some Street, London',
                        'geometry' => ['location' => ['lat' => 51.5, 'lng' => -0.12]],
                        'address_components' => [
                            ['types' => ['country'], 'short_name' => 'GB'],
                            // no locality, only postal_town -> locality should fall back
                            ['types' => ['postal_town'], 'long_name' => 'London'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $r = (new GoogleGeocodingProvider)->reverseGeocode(51.5, -0.12);

        $this->assertNotNull($r);
        $this->assertSame('GB', $r->countryCode);
        $this->assertSame('London', $r->locality);
        $this->assertNull($r->postalCode);

        Http::assertSent(fn (Request $request) => $request['latlng'] === '51.5,-0.12');
    }

    public function test_reverse_geocode_returns_null_when_no_results(): void
    {
        $this->withApiKey();
        Http::fake(['*geocode*' => Http::response(['results' => []], 200)]);

        $this->assertNull((new GoogleGeocodingProvider)->reverseGeocode(0.0, 0.0));
    }

    public function test_reverse_geocode_returns_null_on_unsuccessful_response(): void
    {
        $this->withApiKey();
        Http::fake(['*' => Http::response('', 500)]);

        $this->assertNull((new GoogleGeocodingProvider)->reverseGeocode(1.0, 2.0));
    }

    public function test_reverse_geocode_returns_null_on_exception(): void
    {
        $this->withApiKey();
        Http::fake(['*' => fn () => throw new \RuntimeException('boom')]);

        $this->assertNull((new GoogleGeocodingProvider)->reverseGeocode(1.0, 2.0));
    }

    // ---- distance --------------------------------------------------------

    public function test_distance_returns_null_without_api_key(): void
    {
        Http::fake();
        $this->assertNull((new GoogleGeocodingProvider)->distance(50.8, 4.3, 51.2, 4.4));
        Http::assertNothingSent();
    }

    public function test_distance_maps_ok_element(): void
    {
        $this->withApiKey();
        Http::fake([
            '*distancematrix*' => Http::response([
                'rows' => [
                    [
                        'elements' => [
                            [
                                'status' => 'OK',
                                'distance' => ['value' => 42000, 'text' => '42 km'],
                                'duration' => ['value' => 3000, 'text' => '50 mins'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $r = (new GoogleGeocodingProvider)->distance(50.8467, 4.3525, 51.2194, 4.4025, 'walking');

        $this->assertNotNull($r);
        $this->assertSame(42000, $r->distanceMeters);
        $this->assertSame(3000, $r->durationSeconds);
        $this->assertSame('walking', $r->mode);
        $this->assertSame('google', $r->provider);

        Http::assertSent(function (Request $request) {
            return $request['origins'] === '50.8467,4.3525'
                && $request['destinations'] === '51.2194,4.4025'
                && $request['mode'] === 'walking';
        });
    }

    public function test_distance_returns_null_when_element_status_not_ok(): void
    {
        $this->withApiKey();
        Http::fake([
            '*distancematrix*' => Http::response([
                'rows' => [['elements' => [['status' => 'ZERO_RESULTS']]]],
            ], 200),
        ]);

        $this->assertNull((new GoogleGeocodingProvider)->distance(50.8, 4.3, 51.2, 4.4));
    }

    public function test_distance_returns_null_when_no_row(): void
    {
        $this->withApiKey();
        Http::fake(['*distancematrix*' => Http::response(['rows' => []], 200)]);

        $this->assertNull((new GoogleGeocodingProvider)->distance(50.8, 4.3, 51.2, 4.4));
    }

    public function test_distance_returns_null_on_unsuccessful_response(): void
    {
        $this->withApiKey();
        Http::fake(['*' => Http::response('', 500)]);

        $this->assertNull((new GoogleGeocodingProvider)->distance(50.8, 4.3, 51.2, 4.4));
    }

    public function test_distance_returns_null_on_exception(): void
    {
        $this->withApiKey();
        Http::fake(['*' => fn () => throw new \RuntimeException('boom')]);

        $this->assertNull((new GoogleGeocodingProvider)->distance(50.8, 4.3, 51.2, 4.4));
    }
}
