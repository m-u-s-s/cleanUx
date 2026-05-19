<?php

namespace App\Services\GeolocationV2;

use App\Services\GeolocationV2\Support\Haversine;

class DistanceCalculator
{
    /**
     * Filtre les points dans un rayon donné autour d'une origine (haversine).
     * Retourne les points enrichis avec _distance_meters trié ascendant.
     *
     * @param array<int, array{latitude:float, longitude:float}> $points
     * @return array<int, array>
     */
    public function withinRadius(float $originLat, float $originLng, array $points, float $radiusMeters): array
    {
        $out = [];
        foreach ($points as $p) {
            $lat = (float) ($p['latitude'] ?? 0);
            $lng = (float) ($p['longitude'] ?? 0);
            $d = Haversine::distanceMeters($originLat, $originLng, $lat, $lng);
            if ($d <= $radiusMeters) {
                $p['_distance_meters'] = (int) round($d);
                $out[] = $p;
            }
        }
        usort($out, fn ($a, $b) => $a['_distance_meters'] <=> $b['_distance_meters']);
        return $out;
    }

    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return Haversine::distanceMeters($lat1, $lng1, $lat2, $lng2);
    }

    public function bearingDegrees(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return Haversine::bearingDegrees($lat1, $lng1, $lat2, $lng2);
    }
}
