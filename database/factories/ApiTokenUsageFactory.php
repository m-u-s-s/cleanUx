<?php

namespace Database\Factories;

use App\Models\ApiTokenUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiTokenUsage>
 */
class ApiTokenUsageFactory extends Factory
{
    protected $model = ApiTokenUsage::class;

    public function definition(): array
    {
        return [
            'token_id' => fake()->numberBetween(1, 1000),
            'route_path' => '/api/'.fake()->randomElement(['bookings', 'missions', 'providers', 'users']),
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'response_status' => fake()->randomElement([200, 201, 204, 400, 401, 403, 404, 422, 500]),
            'latency_ms' => fake()->numberBetween(10, 2000),
            'response_size_bytes' => fake()->numberBetween(100, 50000),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_short' => fake()->randomElement(['PHP/8.2', 'curl/8.0', 'axios/1.6', 'Postman']),
            'metadata' => null,
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function failed(): static
    {
        return $this->state(['response_status' => fake()->randomElement([400, 401, 403, 404, 500])]);
    }

    public function success(): static
    {
        return $this->state(['response_status' => fake()->randomElement([200, 201, 204])]);
    }
}
