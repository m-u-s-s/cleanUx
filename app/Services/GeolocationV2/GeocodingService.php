<?php

namespace App\Services\GeolocationV2;

use App\Models\AddressLookup;
use App\Models\DistanceCalculation;
use App\Models\GeocodingCacheEntry;
use App\Services\GeolocationV2\Contracts\GeocodingProviderContract;
use App\Services\GeolocationV2\Support\Haversine;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    public function __construct(protected GeocodingProviderContract $provider) {}

    /**
     * @return AddressSuggestion[]
     */
    public function autocomplete(string $query, ?string $countryCode = null, ?int $limit = null): array
    {
        $minChars = (int) config('geolocation_v2.autocomplete_min_chars', 3);
        $query = trim($query);
        if (mb_strlen($query) < $minChars) {
            return [];
        }
        $limit = $limit ?? (int) config('geolocation_v2.autocomplete_max_results', 8);
        $countryCode = $this->normalizeCountryCode($countryCode);
        $providerName = $this->provider->name();

        $hash = $this->hashQuery($query, $countryCode);

        $cached = AddressLookup::query()
            ->where('provider', $providerName)
            ->where('query_hash', $hash)
            ->fresh()
            ->first();
        if ($cached) {
            return $this->rehydrateSuggestions($cached->results);
        }

        try {
            $results = $this->provider->autocomplete($query, $countryCode, $limit);
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] autocomplete provider error', ['error' => $e->getMessage()]);
            return [];
        }

        AddressLookup::query()->updateOrCreate([
            'provider' => $providerName,
            'query_hash' => $hash,
        ], [
            'query' => mb_substr($query, 0, 191),
            'country_code' => $countryCode,
            'results' => array_map(fn (AddressSuggestion $s) => $s->toArray(), $results),
            'result_count' => count($results),
            'queried_at' => now(),
            'expires_at' => now()->addMinutes((int) config('geolocation_v2.autocomplete_cache_ttl_minutes', 60)),
        ]);

        return $results;
    }

    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }
        $countryCode = $this->normalizeCountryCode($countryCode);
        $providerName = $this->provider->name();
        $hash = $this->hashAddress($address, $countryCode);

        $cached = GeocodingCacheEntry::query()
            ->where('provider', $providerName)
            ->where('address_hash', $hash)
            ->fresh()
            ->first();
        if ($cached) {
            return $this->cacheEntryToResult($cached, $providerName);
        }

        try {
            $result = $this->provider->geocode($address, $countryCode);
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] geocode provider error', ['error' => $e->getMessage()]);
            return null;
        }
        if (! $result) {
            return null;
        }

        GeocodingCacheEntry::query()->updateOrCreate([
            'provider' => $providerName,
            'address_hash' => $hash,
        ], [
            'address_input' => mb_substr($address, 0, 500),
            'country_code' => $countryCode ?? $result->countryCode,
            'latitude' => $result->latitude,
            'longitude' => $result->longitude,
            'formatted_address' => $result->formattedAddress,
            'place_id' => $result->placeId,
            'postal_code' => $result->postalCode,
            'locality' => $result->locality,
            'components' => $result->components,
            'raw' => $result->raw,
            'expires_at' => now()->addMinutes((int) config('geolocation_v2.cache_ttl_minutes', 1440)),
        ]);

        return $result;
    }

    public function reverseGeocode(float $latitude, float $longitude): ?GeocodingResult
    {
        $providerName = $this->provider->name();
        $hash = $this->hashAddress('rev:' . round($latitude, 4) . ',' . round($longitude, 4), null);
        $cached = GeocodingCacheEntry::query()
            ->where('provider', $providerName)
            ->where('address_hash', $hash)
            ->fresh()
            ->first();
        if ($cached) {
            return $this->cacheEntryToResult($cached, $providerName);
        }

        try {
            $result = $this->provider->reverseGeocode($latitude, $longitude);
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] reverse geocode error', ['error' => $e->getMessage()]);
            return null;
        }
        if (! $result) {
            return null;
        }

        GeocodingCacheEntry::query()->updateOrCreate([
            'provider' => $providerName,
            'address_hash' => $hash,
        ], [
            'address_input' => 'rev:' . $latitude . ',' . $longitude,
            'country_code' => $result->countryCode,
            'latitude' => $result->latitude,
            'longitude' => $result->longitude,
            'formatted_address' => $result->formattedAddress,
            'place_id' => $result->placeId,
            'postal_code' => $result->postalCode,
            'locality' => $result->locality,
            'components' => $result->components,
            'raw' => $result->raw,
            'expires_at' => now()->addMinutes((int) config('geolocation_v2.cache_ttl_minutes', 1440)),
        ]);

        return $result;
    }

    public function distance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        ?string $mode = null,
    ): DistanceResult {
        $mode = $mode && in_array($mode, (array) config('geolocation_v2.distance_modes', []), true)
            ? $mode
            : (string) config('geolocation_v2.distance_default_mode', 'driving');
        $providerName = $this->provider->name();
        $sig = $this->hashDistanceSignature($originLat, $originLng, $destLat, $destLng, $mode);

        $cached = DistanceCalculation::query()
            ->where('provider', $providerName)
            ->where('signature_hash', $sig)
            ->fresh()
            ->first();
        if ($cached) {
            return new DistanceResult(
                distanceMeters: $cached->distance_meters,
                durationSeconds: $cached->duration_seconds,
                mode: $cached->mode,
                provider: $providerName,
                isFallbackHaversine: $cached->is_fallback_haversine,
            );
        }

        $result = null;
        try {
            $result = $this->provider->distance($originLat, $originLng, $destLat, $destLng, $mode);
        } catch (\Throwable $e) {
            Log::warning('[geo_v2] distance provider error', ['error' => $e->getMessage()]);
        }
        $isFallback = false;
        if (! $result && (bool) config('geolocation_v2.haversine_fallback_enabled', true)) {
            $meters = (int) round(Haversine::distanceMeters($originLat, $originLng, $destLat, $destLng));
            $avgSpeed = (float) (config('geolocation_v2.isochrone_avg_speed_kmh.' . $mode, 35));
            $durationSec = $avgSpeed > 0 ? (int) round(($meters / 1000) / $avgSpeed * 3600) : null;
            $result = new DistanceResult($meters, $durationSec, $mode, $providerName, true);
            $isFallback = true;
        }
        if (! $result) {
            // graceful : pas de cache row, retourner haversine zero pour ne pas crash caller
            return new DistanceResult(0, null, $mode, $providerName, true);
        }

        DistanceCalculation::query()->updateOrCreate([
            'provider' => $providerName,
            'signature_hash' => $sig,
        ], [
            'origin_lat' => $originLat,
            'origin_lng' => $originLng,
            'dest_lat' => $destLat,
            'dest_lng' => $destLng,
            'mode' => $mode,
            'distance_meters' => $result->distanceMeters,
            'duration_seconds' => $result->durationSeconds,
            'is_fallback_haversine' => $isFallback || $result->isFallbackHaversine,
            'raw' => $result->raw,
            'expires_at' => now()->addMinutes((int) config('geolocation_v2.distance_cache_ttl_minutes', 360)),
        ]);

        return $result;
    }

    /**
     * Estime rayon approximatif en mètres pour atteindre N minutes en mode donné.
     * Pas un vrai isochrone routing, mais un cercle conservateur.
     */
    public function isochroneRadiusMeters(int $minutes, string $mode = 'driving'): int
    {
        $avgSpeed = (float) (config('geolocation_v2.isochrone_avg_speed_kmh.' . $mode, 35));
        return (int) round(($minutes / 60) * $avgSpeed * 1000);
    }

    public function purgeExpired(): array
    {
        $a = AddressLookup::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();
        $g = GeocodingCacheEntry::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();
        $d = DistanceCalculation::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->delete();
        return ['address_lookups' => $a, 'geocoding_results' => $g, 'distance_calculations' => $d];
    }

    private function hashQuery(string $q, ?string $country): string
    {
        return hash('sha256', mb_strtolower($q) . '|' . ($country ?? ''));
    }

    private function hashAddress(string $a, ?string $country): string
    {
        return hash('sha256', mb_strtolower(trim($a)) . '|' . ($country ?? ''));
    }

    private function hashDistanceSignature(float $oLat, float $oLng, float $dLat, float $dLng, string $mode): string
    {
        $key = sprintf('%.5f|%.5f|%.5f|%.5f|%s', $oLat, $oLng, $dLat, $dLng, $mode);
        return hash('sha256', $key);
    }

    private function normalizeCountryCode(?string $country): ?string
    {
        if (! $country) {
            return null;
        }
        $upper = strtoupper(trim($country));
        $allowed = (array) config('geolocation_v2.allowed_country_codes', []);
        if (! empty($allowed) && ! in_array($upper, $allowed, true)) {
            return null;
        }
        return $upper;
    }

    private function rehydrateSuggestions(array $rows): array
    {
        return array_map(function (array $r) {
            return new AddressSuggestion(
                description: (string) ($r['description'] ?? ''),
                placeId: $r['place_id'] ?? null,
                countryCode: $r['country_code'] ?? null,
                mainText: $r['main_text'] ?? null,
                secondaryText: $r['secondary_text'] ?? null,
                postalCode: $r['postal_code'] ?? null,
                latitude: isset($r['latitude']) ? (float) $r['latitude'] : null,
                longitude: isset($r['longitude']) ? (float) $r['longitude'] : null,
                provider: (string) ($r['provider'] ?? 'cache'),
            );
        }, $rows);
    }

    private function cacheEntryToResult(GeocodingCacheEntry $cached, string $providerName): GeocodingResult
    {
        return new GeocodingResult(
            latitude: (float) $cached->latitude,
            longitude: (float) $cached->longitude,
            formattedAddress: $cached->formatted_address,
            placeId: $cached->place_id,
            countryCode: $cached->country_code,
            postalCode: $cached->postal_code,
            locality: $cached->locality,
            components: (array) ($cached->components ?? []),
            provider: $providerName,
            raw: (array) ($cached->raw ?? []),
        );
    }
}
