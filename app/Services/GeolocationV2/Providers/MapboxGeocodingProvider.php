<?php

namespace App\Services\GeolocationV2\Providers;

use App\Services\GeolocationV2\AddressSuggestion;
use App\Services\GeolocationV2\Contracts\GeocodingProviderContract;
use App\Services\GeolocationV2\DistanceResult;
use App\Services\GeolocationV2\GeocodingResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mapbox Geocoding + Directions Matrix.
 * Skeleton — appel HTTP réel via Illuminate\Http. Nécessite MAPBOX_ACCESS_TOKEN.
 */
class MapboxGeocodingProvider implements GeocodingProviderContract
{
    public function name(): string
    {
        return 'mapbox';
    }

    public function autocomplete(string $query, ?string $countryCode = null, int $limit = 8): array
    {
        $cfg = (array) config('geolocation_v2.providers.mapbox');
        $token = $cfg['access_token'] ?? null;
        if (! $token) {
            return [];
        }
        $encoded = rawurlencode($query);
        try {
            $url = rtrim($cfg['geocode_endpoint'], '/') . "/{$encoded}.json";
            $response = Http::timeout((int) config('geolocation_v2.timeout_seconds', 8))
                ->get($url, array_filter([
                    'access_token' => $token,
                    'autocomplete' => 'true',
                    'limit' => $limit,
                    'country' => $countryCode ? strtolower($countryCode) : null,
                ]));
            if (! $response->successful()) {
                return [];
            }
            $features = (array) ($response->json('features') ?? []);
            $results = [];
            foreach ($features as $f) {
                $coords = $f['geometry']['coordinates'] ?? [null, null];
                $results[] = new AddressSuggestion(
                    description: (string) ($f['place_name'] ?? ''),
                    placeId: $f['id'] ?? null,
                    countryCode: $this->mapboxCountry($f),
                    mainText: $f['text'] ?? null,
                    secondaryText: $f['place_name'] ?? null,
                    postalCode: $this->extractContext($f, 'postcode'),
                    latitude: isset($coords[1]) ? (float) $coords[1] : null,
                    longitude: isset($coords[0]) ? (float) $coords[0] : null,
                    provider: 'mapbox',
                );
            }
            return $results;
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] mapbox autocomplete error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult
    {
        $suggestions = $this->autocomplete($address, $countryCode, 1);
        $first = $suggestions[0] ?? null;
        if (! $first || $first->latitude === null) {
            return null;
        }
        return new GeocodingResult(
            latitude: $first->latitude,
            longitude: $first->longitude,
            formattedAddress: $first->description,
            placeId: $first->placeId,
            countryCode: $first->countryCode,
            postalCode: $first->postalCode,
            locality: $first->mainText,
            provider: 'mapbox',
        );
    }

    public function reverseGeocode(float $latitude, float $longitude): ?GeocodingResult
    {
        $cfg = (array) config('geolocation_v2.providers.mapbox');
        $token = $cfg['access_token'] ?? null;
        if (! $token) {
            return null;
        }
        try {
            $url = rtrim($cfg['geocode_endpoint'], '/') . "/{$longitude},{$latitude}.json";
            $response = Http::timeout((int) config('geolocation_v2.timeout_seconds', 8))
                ->get($url, ['access_token' => $token]);
            if (! $response->successful()) {
                return null;
            }
            $first = data_get($response->json(), 'features.0');
            if (! $first) {
                return null;
            }
            $coords = $first['geometry']['coordinates'] ?? [null, null];
            return new GeocodingResult(
                latitude: (float) ($coords[1] ?? 0),
                longitude: (float) ($coords[0] ?? 0),
                formattedAddress: $first['place_name'] ?? null,
                placeId: $first['id'] ?? null,
                countryCode: $this->mapboxCountry($first),
                postalCode: $this->extractContext($first, 'postcode'),
                locality: $this->extractContext($first, 'place'),
                provider: 'mapbox',
                raw: $first,
            );
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] mapbox reverse error', ['error' => $e->getMessage()]);
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
        $cfg = (array) config('geolocation_v2.providers.mapbox');
        $token = $cfg['access_token'] ?? null;
        if (! $token) {
            return null;
        }
        try {
            $profile = match ($mode) {
                'walking' => 'walking',
                'bicycling' => 'cycling',
                default => 'driving',
            };
            $url = rtrim($cfg['directions_endpoint'], '/') . "/{$profile}/{$originLng},{$originLat};{$destLng},{$destLat}";
            $response = Http::timeout((int) config('geolocation_v2.timeout_seconds', 8))
                ->get($url, [
                    'access_token' => $token,
                    'annotations' => 'distance,duration',
                ]);
            if (! $response->successful()) {
                return null;
            }
            $meters = (int) round((float) data_get($response->json(), 'distances.0.1') ?? 0);
            $duration = (int) round((float) data_get($response->json(), 'durations.0.1') ?? 0);
            if ($meters <= 0) {
                return null;
            }
            return new DistanceResult(
                distanceMeters: $meters,
                durationSeconds: $duration ?: null,
                mode: $mode,
                provider: 'mapbox',
            );
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] mapbox distance error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function mapboxCountry(array $feature): ?string
    {
        $iso = $this->extractContext($feature, 'country', 'short_code');
        if ($iso === null) {
            return null;
        }
        $iso = strtoupper((string) $iso);
        return strlen($iso) === 2 ? $iso : null;
    }

    private function extractContext(array $feature, string $type, string $field = 'text'): ?string
    {
        $context = (array) ($feature['context'] ?? []);
        foreach ($context as $c) {
            $id = (string) ($c['id'] ?? '');
            if (str_starts_with($id, $type . '.')) {
                return $c[$field] ?? ($c['text'] ?? null);
            }
        }
        return null;
    }
}
