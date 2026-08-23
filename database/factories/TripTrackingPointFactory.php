<?php

namespace Database\Factories;

use App\Models\TripTrackingPoint;
use App\Models\TripTrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripTrackingPointFactory extends Factory
{
    protected $model = TripTrackingPoint::class;

    /** Compteur monotone pour `client_sequence`. */
    private static int $nextSequence = 1;

    public function definition(): array
    {
        return [
            'session_id' => fn () => TripTrackingSession::factory()->create()->id,
            'lat' => fake()->latitude(49.5, 51.5),
            'lng' => fake()->longitude(2.5, 6.5),
            'accuracy_m' => fake()->randomFloat(1, 3.0, 50.0),
            'speed_mps' => fake()->randomFloat(2, 0.0, 20.0),
            'heading_deg' => fake()->numberBetween(0, 359),
            'cumulative_distance_m' => fake()->numberBetween(0, 5000),
            'distance_to_dest_m' => fake()->numberBetween(100, 10000),
            'eta_seconds' => fake()->numberBetween(60, 3600),
            'client_sequence' => self::$nextSequence++,
            'recorded_at' => now(),
            'created_at' => now(),
        ];
    }
}
