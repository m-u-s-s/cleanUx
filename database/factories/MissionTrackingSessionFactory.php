<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionTrackingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionTrackingSessionFactory extends Factory
{
    protected $model = MissionTrackingSession::class;

    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'employee_user_id' => User::factory(),
            'is_client_visible' => true,
            'is_active' => true,
            'started_at' => now(),
            'start_lat' => fake()->latitude(50.0, 51.5),
            'start_lng' => fake()->longitude(3.0, 5.5),
            'last_lat' => fake()->latitude(50.0, 51.5),
            'last_lng' => fake()->longitude(3.0, 5.5),
            'point_count' => 0,
            'distance_meters' => 0,
            'tracking_mode' => 'gps',
            'meta' => [],
        ];
    }
}
