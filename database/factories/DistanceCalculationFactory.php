<?php

namespace Database\Factories;

use App\Models\DistanceCalculation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DistanceCalculationFactory extends Factory
{
    protected $model = DistanceCalculation::class;

    public function definition(): array
    {
        $originLat = fake()->latitude(49.5, 51.5);
        $originLng = fake()->longitude(2.5, 6.5);
        $destLat = fake()->latitude(49.5, 51.5);
        $destLng = fake()->longitude(2.5, 6.5);

        return [
            'provider' => fake()->randomElement(['mock', 'google', 'mapbox']),
            'signature_hash' => hash('sha256', implode('|', [$originLat, $originLng, $destLat, $destLng])),
            'origin_lat' => $originLat,
            'origin_lng' => $originLng,
            'dest_lat' => $destLat,
            'dest_lng' => $destLng,
            'mode' => 'driving',
            'distance_meters' => fake()->numberBetween(500, 50000),
            'duration_seconds' => fake()->numberBetween(120, 7200),
            'is_fallback_haversine' => false,
            'raw' => [],
            'expires_at' => now()->addDays(7),
        ];
    }
}
