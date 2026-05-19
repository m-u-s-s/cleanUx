<?php

namespace App\Services\GeolocationV2\Support;

class Haversine
{
    /**
     * Distance to-fly (great-circle) between two coordinates in meters.
     */
    public static function distanceMeters(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
        ?float $earthRadiusMeters = null,
    ): float {
        $R = $earthRadiusMeters ?? (float) (config('geolocation_v2.earth_radius_meters', 6_371_000));
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }

    /**
     * Initial bearing in degrees (0-360) from (lat1,lng1) to (lat2,lng2).
     */
    public static function bearingDegrees(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLng = deg2rad($lng2 - $lng1);

        $y = sin($dLng) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($dLng);
        $bearing = atan2($y, $x);
        return fmod(rad2deg($bearing) + 360, 360);
    }
}
