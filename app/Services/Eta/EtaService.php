<?php

namespace App\Services\Eta;

use App\Models\Mission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Phase 13 — Calcul d'ETA pour une mission en cours. Stratégie : 1. */
class EtaService
{
    public const CACHE_TTL_SECONDS = 60;

    public const DEFAULT_AVG_SPEED_KMH = 30;

    /** Calcule ou récupère l'ETA d'une mission depuis sa session de tracking active. */
    public function computeForMission(Mission $mission, bool $force = false): array
    {

        $session = $mission->trackingSessions()
            ->where('is_active', true)
            ->latest('started_at')
            ->first();

        if (! $session) {
            return $this->emptyResult();
        }

        $tracking = $mission->trackingSessions()
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhere('status', 'active');
            })
            ->latest('started_at')
            ->first();

        $originLat = $tracking?->last_lat
            ?? $tracking?->current_lat
            ?? $tracking?->start_lat;

        $originLng = $tracking?->last_lng
            ?? $tracking?->current_lng
            ?? $tracking?->start_lng;

        $booking = $mission->booking
            ?? $mission->booking
            ?? null;

        $destinationLat = $mission->destination_lat
            ?? $mission->end_lat
            ?? $booking?->destination_lat
            ?? $booking?->latitude
            ?? $booking?->lat;

        $destinationLng = $mission->destination_lng
            ?? $mission->end_lng
            ?? $booking?->destination_lng
            ?? $booking?->longitude
            ?? $booking?->lng;

        $providerLat = $session->last_lat ? (float) $session->last_lat : null;
        $providerLng = $session->last_lng ? (float) $session->last_lng : null;

        $booking = $mission->booking;
        $destLat = $booking?->destination_lat ? (float) $booking->destination_lat : null;
        $destLng = $booking?->destination_lng ? (float) $booking->destination_lng : null;

        if (! $providerLat || ! $providerLng || ! $destLat || ! $destLng) {
            return $this->emptyResult();
        }

        $cacheKey = "eta:mission:{$mission->id}:".md5("$providerLat,$providerLng,$destLat,$destLng");

        if (! $force && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $cached['source'] = 'cache';

            return $cached;
        }

        $result = $this->computeBetween($providerLat, $providerLng, $destLat, $destLng);

        if ($result['meters'] !== null) {
            Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);

            // Persiste sur la mission pour les requêtes ultérieures sans recalcul
            $mission->update([
                'last_eta_meters' => $result['meters'],
                'last_eta_seconds' => $result['seconds'],
                'last_eta_source' => $result['source'],
                'last_eta_calculated_at' => now(),
            ]);
        }

        return $result;
    }

    /** Calcul brut entre 2 points (lat/lng). */
    public function computeBetween(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        // config/services.php définit `google_maps.key`, pas `google_maps.api_key` :
        // l'ancienne clé rendait TOUJOURS null et seul le repli env() fonctionnait —
        // repli qui se serait tu dès `config:cache`, laissant l'ETA en Haversine
        // permanent sans le moindre signal.
        $googleKey = (string) config('services.google_maps.key');

        if ($googleKey !== '') {
            $google = $this->callDistanceMatrix($fromLat, $fromLng, $toLat, $toLng, $googleKey);
            if ($google) {
                return $google;
            }
        }

        // Fallback Haversine
        return $this->haversine($fromLat, $fromLng, $toLat, $toLng);
    }

    /** Appelle Google Distance Matrix API. */
    protected function callDistanceMatrix(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        string $apiKey,
    ): ?array {
        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => "$fromLat,$fromLng",
                'destinations' => "$toLat,$toLng",
                'mode' => 'driving',
                'units' => 'metric',
                'departure_time' => 'now',
                'key' => $apiKey,
            ]);

            if (! $response->successful()) {
                Log::warning('EtaService: Google Distance Matrix HTTP error', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK') {
                Log::warning('EtaService: Google Distance Matrix non-OK', [
                    'status' => $data['status'] ?? null,
                ]);

                return null;
            }

            $element = $data['rows'][0]['elements'][0] ?? null;

            if (! $element || ($element['status'] ?? '') !== 'OK') {
                return null;
            }

            // duration_in_traffic > duration si dispo (compte du trafic actuel)
            $seconds = $element['duration_in_traffic']['value']
                ?? $element['duration']['value']
                ?? null;
            $meters = $element['distance']['value'] ?? null;

            if ($seconds === null || $meters === null) {
                return null;
            }

            return [
                'meters' => (int) $meters,
                'seconds' => (int) $seconds,
                'source' => 'google',
                'calculated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning('EtaService: Google Distance Matrix exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Fallback Haversine + vitesse moyenne fixe. */
    protected function haversine(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        $distanceKm = $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
        $meters = (int) round($distanceKm * 1000);
        $seconds = (int) round(($distanceKm / self::DEFAULT_AVG_SPEED_KMH) * 3600);

        return [
            'meters' => $meters,
            'seconds' => $seconds,
            'source' => 'haversine',
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    protected function emptyResult(): array
    {
        return [
            'meters' => null,
            'seconds' => null,
            'source' => 'none',
            'calculated_at' => null,
        ];
    }
}
