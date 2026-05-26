<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['login', 'logout', 'create', 'update', 'delete', 'view', 'export']),
            'domain' => fake()->randomElement(['auth', 'booking', 'mission', 'payment', 'admin', 'profile']),
            'severity' => fake()->randomElement(['info', 'warning', 'critical']),
            'is_critical' => fake()->boolean(10),
            'target_type' => fake()->optional()->randomElement(['App\\Models\\Booking', 'App\\Models\\Mission', 'App\\Models\\User']),
            'target_id' => fake()->optional()->numberBetween(1, 1000),
            'route_name' => fake()->optional()->word(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'request_id' => fake()->uuid(),
            'meta' => [],
        ];
    }
}
