<?php

namespace App\Services\GeolocationV2\Providers;

use App\Services\GeolocationV2\AddressSuggestion;
use App\Services\GeolocationV2\Contracts\GeocodingProviderContract;
use App\Services\GeolocationV2\DistanceResult;
use App\Services\GeolocationV2\GeocodingResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Maps Places + Geocoding + Distance Matrix.
 * Skeleton — appel HTTP réel via Illuminate\Http. Nécessite GOOGLE_MAPS_API_KEY.
 */
class GoogleGeocodingProvider implements GeocodingProviderContract
{
    public function name(): string
    {
        return 'google';
    }

    public function autocomplete(string $query, ?string $countryCode = null, int $limit = 8): array
    {
        $cfg = (array) config('geolocation_v2.providers.google');
        $apiKey = $cfg['api_key'] ?? null;
        if (! $apiKey) {
            return [];
        }
        try {
            $response = Http::timeout((int) config('geolocation_v2.timeout_seconds', 8))
                ->get($cfg['places_endpoint'], array_filter([
                    'input' => $query,
                    'key' => $apiKey,
                    'language' => $cfg['language'] ?? 'fr',
                    'components' => $countryCode ? 'country:' . strtolower($countryCode) : null,
                ]));
            if (! $response->successful()) {
                return [];
            }
            $predictions = (array) ($response->json('predictions') ?? []);
            $results = [];
            foreach (array_slice($predictions, 0, $limit) as $p) {
                $results[] = new AddressSuggestion(
                    description: (string) ($p['description'] ?? ''),
                    placeId: $p['place_id'] ?? null,
                    countryCode: $countryCode,
                    mainText: data_get($p, 'structured_formatting.main_text'),
                    secondaryText: data_get($p, 'structured_formatting.secondary_text'),
                    provider: 'google',
                );
            }
            return $results;
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] google autocomplete error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult
    {
        $cfg = (array) config('geolocation_v2.providers.google');
        $apiKey = $cfg['api_key'] ?? null;
        if (! $apiKey) {
            return null;
        }
        try {
            $response = Http::timeout((int) config('geolocation_v2.timeout_seconds', 8))
                ->get($cfg['geocode_endpoint'], array_filter([
                    'address' => $address,
                    'key' => $apiKey,
                    'language' => $cfg['language'] ?? 'fr',
                    'region' => $countryCode ? strtolower($countryCode) : null,
                ]));
            if (! $response->successful()) {
                return null;
            }
            $first = data_get($response->json(), 'results.0');
            if (! $first) {
                return null;
            }
            return $this->mapResult($first);
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] google geocode error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function reverseGeocode(float $latitude, float $longitude): ?GeocodingResult
    {
        $cfg = (array) config('geolocation_v2.providers.google');
        $apiKey = $cfg['api_key'] ?? null;
        if (! $apiKey) {
            return null;
        }
        try {
            $response = Http::timeout((int) config('geolocation_v2.timeout_seconds', 8))
                ->get($cfg['geocode_endpoint'], [
                    'latlng' => $latitude . ',' . $longitude,
                    'key' => $apiKey,
                    'language' => $cfg['language'] ?? 'fr',
                ]);
            if (! $response->successful()) {
                return null;
            }
            $first = data_get($response->json(), 'results.0');
            return $first ? $this->mapResult($first) : null;
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] google reverse error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function distance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        string $mode = 'driving',
    ): ?DistanceResult {
        $cfg = (array) config('geolocation_v2.providers.google');
        $apiKey = $cfg['api_key'] ?? null;
        if (! $apiKey) {
            return null;
        }
        try {
            $response = Http::timeout((int) config('geolocation_v2.timeout_seconds', 8))
                ->get($cfg['distance_endpoint'], [
                    'origins' => $originLat . ',' . $originLng,
                    'destinations' => $destLat . ',' . $destLng,
                    'mode' => $mode,
                    'key' => $apiKey,
                ]);
            if (! $response->successful()) {
                return null;
            }
            $row = data_get($response->json(), 'rows.0.elements.0');
            if (! $row || ($row['status'] ?? null) !== 'OK') {
                return null;
            }
            return new DistanceResult(
                distanceMeters: (int) data_get($row, 'distance.value'),
                durationSeconds: (int) data_get($row, 'duration.value'),
                mode: $mode,
                provider: 'google',
                raw: $row,
            );
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] google distance error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function mapResult(array $first): GeocodingResult
    {
        $components = (array) ($first['address_components'] ?? []);
        $getComponent = function (string $type) use ($components) {
            foreach ($components as $c) {
                if (in_array($type, (array) ($c['types'] ?? []), true)) {
                    return $c['short_name'] ?? $c['long_name'] ?? null;
                }
            }
            return null;
        };
        return new GeocodingResult(
            latitude: (float) data_get($first, 'geometry.location.lat'),
            longitude: (float) data_get($first, 'geometry.location.lng'),
            formattedAddress: $first['formatted_address'] ?? null,
            placeId: $first['place_id'] ?? null,
            countryCode: $getComponent('country'),
            postalCode: $getComponent('postal_code'),
            locality: $getComponent('locality') ?? $getComponent('postal_town'),
            components: $components,
            provider: 'google',
            raw: $first,
        );
    }
}
