<?php

namespace App\Services\Matching;

use App\Models\Booking;
use App\Models\User;
use App\Services\GeolocationV2\DistanceCalculator;
use App\Services\GeolocationV2\GeocodingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Enrichit MatchingScoreEngine avec un score basé sur distance géographique réelle. */
class GeoMatchingEnhancer
{
    public function __construct(
        protected DistanceCalculator $distance,
        protected GeocodingService $geocoding,
    ) {}

    /** Retourne un score 0-100 selon distance provider↔booking. */
    public function proximityScore(User $provider, Booking $booking): ?float
    {
        $providerCoords = $this->coordsFor($provider);
        $bookingCoords = $this->coordsForBooking($booking);
        if (! $providerCoords || ! $bookingCoords) {
            return null;
        }

        $meters = $this->distance->distanceMeters(
            $providerCoords['lat'],
            $providerCoords['lng'],
            $bookingCoords['lat'],
            $bookingCoords['lng'],
        );
        $km = $meters / 1000;

        return match (true) {
            $km <= 5 => 100.0,
            $km <= 15 => round(90 - (($km - 5) / 10) * 30, 1),
            $km <= 30 => round(60 - (($km - 15) / 15) * 30, 1),
            $km <= 50 => round(30 - (($km - 30) / 20) * 20, 1),
            default => 0.0,
        };
    }

    /**
     * Filtre une liste de providers selon rayon en km.
     *
     * @param  iterable<User>  $providers
     * @return array<int, array{provider:User, distance_km:float}>
     */
    public function filterWithinRadius(iterable $providers, Booking $booking, float $radiusKm): array
    {
        $bookingCoords = $this->coordsForBooking($booking);
        if (! $bookingCoords) {
            return [];
        }
        $out = [];
        foreach ($providers as $p) {
            $coords = $this->coordsFor($p);
            if (! $coords) {
                continue;
            }
            $meters = $this->distance->distanceMeters(
                $bookingCoords['lat'], $bookingCoords['lng'],
                $coords['lat'], $coords['lng'],
            );
            $km = $meters / 1000;
            if ($km <= $radiusKm) {
                $out[] = ['provider' => $p, 'distance_km' => round($km, 2)];
            }
        }
        usort($out, fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return $out;
    }

    /** Coordonnées pour un provider : on essaie les colonnes courantes (latitude/longitude direct, OU base_address geocodable). */
    protected function coordsFor(User $provider): ?array
    {
        if (isset($provider->latitude, $provider->longitude)
            && is_numeric($provider->latitude) && is_numeric($provider->longitude)) {
            return ['lat' => (float) $provider->latitude, 'lng' => (float) $provider->longitude];
        }
        // ProviderProfile relation
        $profile = $provider->relationLoaded('providerProfile') ? $provider->providerProfile : null;
        if (! $profile && method_exists($provider, 'providerProfile')) {
            try {
                $profile = $provider->providerProfile;
            } catch (\Throwable) {
                $profile = null;
            }
        }
        if ($profile && isset($profile->latitude, $profile->longitude)
            && is_numeric($profile->latitude) && is_numeric($profile->longitude)) {
            return ['lat' => (float) $profile->latitude, 'lng' => (float) $profile->longitude];
        }
        // Geocode du base_address si dispo
        $address = $profile->base_address ?? $provider->base_address ?? null;
        if ($address && Schema::hasTable('geocoding_results')) {
            try {
                $result = $this->geocoding->geocode((string) $address);
                if ($result) {
                    return ['lat' => $result->latitude, 'lng' => $result->longitude];
                }
            } catch (\Throwable $e) {
                Log::warning('[geo_matching] provider geocode failed', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    protected function coordsForBooking(Booking $booking): ?array
    {
        if (isset($booking->latitude, $booking->longitude)
            && is_numeric($booking->latitude) && is_numeric($booking->longitude)) {
            return ['lat' => (float) $booking->latitude, 'lng' => (float) $booking->longitude];
        }
        $address = $booking->address ?? $booking->client_address ?? null;
        if ($address && Schema::hasTable('geocoding_results')) {
            try {
                $result = $this->geocoding->geocode((string) $address);
                if ($result) {
                    return ['lat' => $result->latitude, 'lng' => $result->longitude];
                }
            } catch (\Throwable $e) {
                Log::warning('[geo_matching] booking geocode failed', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }
}
