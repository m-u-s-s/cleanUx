<?php

namespace App\Services\GeolocationV2\Providers;

use App\Services\GeolocationV2\AddressSuggestion;
use App\Services\GeolocationV2\Contracts\GeocodingProviderContract;
use App\Services\GeolocationV2\DistanceResult;
use App\Services\GeolocationV2\GeocodingResult;
use App\Services\GeolocationV2\Support\Haversine;
use Illuminate\Support\Str;

/**
 * Mock provider — canned data for FR/BE/NL cities. Used in CI/dev/fallback.
 */
class MockGeocodingProvider implements GeocodingProviderContract
{
    /** @var array<string, array{lat:float,lng:float,postal:string,locality:string,country:string,formatted:string}> */
    protected static array $catalog = [
        '1000 bruxelles' => ['lat' => 50.8467, 'lng' => 4.3525, 'postal' => '1000', 'locality' => 'Bruxelles', 'country' => 'BE', 'formatted' => '1000 Bruxelles, Belgique'],
        '1050 ixelles' => ['lat' => 50.8333, 'lng' => 4.3667, 'postal' => '1050', 'locality' => 'Ixelles', 'country' => 'BE', 'formatted' => '1050 Ixelles, Belgique'],
        '2000 anvers' => ['lat' => 51.2194, 'lng' => 4.4025, 'postal' => '2000', 'locality' => 'Anvers', 'country' => 'BE', 'formatted' => '2000 Anvers, Belgique'],
        '4000 liege' => ['lat' => 50.6326, 'lng' => 5.5797, 'postal' => '4000', 'locality' => 'Liège', 'country' => 'BE', 'formatted' => '4000 Liège, Belgique'],
        '75001 paris' => ['lat' => 48.8606, 'lng' => 2.3376, 'postal' => '75001', 'locality' => 'Paris', 'country' => 'FR', 'formatted' => '75001 Paris, France'],
        '69001 lyon' => ['lat' => 45.7656, 'lng' => 4.8324, 'postal' => '69001', 'locality' => 'Lyon', 'country' => 'FR', 'formatted' => '69001 Lyon, France'],
        '1011 amsterdam' => ['lat' => 52.3729, 'lng' => 4.8936, 'postal' => '1011', 'locality' => 'Amsterdam', 'country' => 'NL', 'formatted' => '1011 Amsterdam, Pays-Bas'],
    ];

    public function name(): string
    {
        return 'mock';
    }

    public function autocomplete(string $query, ?string $countryCode = null, int $limit = 8): array
    {
        $norm = $this->normalize($query);
        if ($norm === '') {
            return [];
        }
        $results = [];
        foreach (self::$catalog as $key => $entry) {
            if ($countryCode && strcasecmp($entry['country'], $countryCode) !== 0) {
                continue;
            }
            if (str_contains($key, $norm) || str_contains($this->normalize($entry['formatted']), $norm)) {
                $results[] = new AddressSuggestion(
                    description: $entry['formatted'],
                    placeId: 'mock_' . Str::slug($key),
                    countryCode: $entry['country'],
                    mainText: $entry['locality'],
                    secondaryText: $entry['postal'] . ', ' . $entry['country'],
                    postalCode: $entry['postal'],
                    latitude: $entry['lat'],
                    longitude: $entry['lng'],
                    provider: 'mock',
                );
            }
            if (count($results) >= $limit) {
                break;
            }
        }
        return $results;
    }

    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult
    {
        $norm = $this->normalize($address);
        foreach (self::$catalog as $key => $entry) {
            if ($countryCode && strcasecmp($entry['country'], $countryCode) !== 0) {
                continue;
            }
            if (str_contains($key, $norm) || str_contains($this->normalize($entry['formatted']), $norm)) {
                return $this->toGeocodingResult($entry);
            }
        }
        return null;
    }

    public function reverseGeocode(float $latitude, float $longitude): ?GeocodingResult
    {
        $best = null;
        $bestDistance = PHP_FLOAT_MAX;
        foreach (self::$catalog as $entry) {
            $d = Haversine::distanceMeters($latitude, $longitude, $entry['lat'], $entry['lng']);
            if ($d < $bestDistance) {
                $bestDistance = $d;
                $best = $entry;
            }
        }
        if (! $best) {
            return null;
        }
        return $this->toGeocodingResult($best);
    }

    public function distance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $mode = 'driving',
    ): ?DistanceResult {
        $meters = (int) round(Haversine::distanceMeters($originLat, $originLng, $destLat, $destLng));
        $avgSpeed = (float) (config('geolocation_v2.isochrone_avg_speed_kmh.' . $mode, 35));
        $durationSec = $avgSpeed > 0 ? (int) round(($meters / 1000) / $avgSpeed * 3600) : null;
        return new DistanceResult(
            distanceMeters: $meters,
            durationSeconds: $durationSec,
            mode: $mode,
            provider: 'mock',
            isFallbackHaversine: false,
        );
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[éèêë]/u', 'e', $s);
        $s = preg_replace('/[àâ]/u', 'a', $s);
        $s = preg_replace('/[ïî]/u', 'i', $s);
        $s = preg_replace('/[ôö]/u', 'o', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return (string) $s;
    }

    private function toGeocodingResult(array $entry): GeocodingResult
    {
        return new GeocodingResult(
            latitude: $entry['lat'],
            longitude: $entry['lng'],
            formattedAddress: $entry['formatted'],
            placeId: 'mock_' . Str::slug($entry['locality'] . '-' . $entry['postal']),
            countryCode: $entry['country'],
            postalCode: $entry['postal'],
            locality: $entry['locality'],
            components: [
                'postal_code' => $entry['postal'],
                'locality' => $entry['locality'],
                'country' => $entry['country'],
            ],
            provider: 'mock',
        );
    }
}
