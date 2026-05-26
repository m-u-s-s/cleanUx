<?php

namespace Database\Factories;

use App\Models\ProviderPresence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderPresenceFactory extends Factory
{
    protected $model = ProviderPresence::class;

    public function definition(): array
    {
        return [
            'provider_user_id' => User::factory(),
            'status' => fake()->randomElement(['online', 'busy', 'on_break', 'offline']),
            'current_lat' => fake()->latitude(50.0, 51.5),
            'current_lng' => fake()->longitude(3.0, 5.5),
            'available_radius_km' => fake()->numberBetween(5, 50),
            'heartbeat_at' => now(),
            'last_status_change_at' => now(),
            'online_minutes_today' => fake()->numberBetween(0, 480),
            'online_minutes_week' => fake()->numberBetween(0, 2400),
            'metadata' => [],
        ];
    }
}
