<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoDistanceService
{
    /**
     * Real driving distance and duration via Google Distance Matrix API.
     *
     * @return array{distance_km: float, duration_minutes: int}|null
     */
    public function drivingDistanceKm(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng
    ): ?array {
        $apiKey = (string) config('services.google.maps_key', config('services.google.maps_api_key', ''));
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)->get(
                'https://maps.googleapis.com/maps/api/distancematrix/json',
                [
                    'origins' => "{$fromLat},{$fromLng}",
                    'destinations' => "{$toLat},{$toLng}",
                    'mode' => 'driving',
                    'units' => 'metric',
                    'key' => $apiKey,
                ]
            );

            if (! $response->ok()) {
                Log::warning('[geo] Google Distance Matrix HTTP error', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();
            $element = data_get($data, 'rows.0.elements.0');

            if (data_get($element, 'status') !== 'OK') {
                Log::info('[geo] Google Distance Matrix element not OK', ['element' => $element]);

                return null;
            }

            $distanceMeters = (int) data_get($element, 'distance.value', 0);
            $durationSeconds = (int) data_get($element, 'duration.value', 0);

            return [
                'distance_km' => round($distanceMeters / 1000, 2),
                'duration_minutes' => (int) ceil($durationSeconds / 60),
            ];
        } catch (\Throwable $e) {
            Log::warning('[geo] drivingDistanceKm failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function haversineKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
