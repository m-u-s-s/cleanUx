<?php

namespace Database\Factories;

use App\Models\MissionTrackingPoint;
use App\Models\MissionTrackingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionTrackingPointFactory extends Factory
{
    protected $model = MissionTrackingPoint::class;

    public function definition(): array
    {
        return [
            'tracking_session_id' => MissionTrackingSession::factory(),
            'recorded_at' => now(),
            'lat' => fake()->latitude(50.0, 51.5),
            'lng' => fake()->longitude(3.0, 5.5),
            'accuracy_meters' => fake()->randomFloat(2, 3, 30),
            'speed_kmh' => fake()->randomFloat(2, 0, 60),
            'heading' => fake()->randomFloat(2, 0, 360),
            'battery_level' => fake()->numberBetween(10, 100),
            'source' => 'gps',
            'meta' => [],
        ];
    }
}
