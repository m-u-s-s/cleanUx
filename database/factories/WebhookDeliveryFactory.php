<?php

namespace Database\Factories;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'endpoint_id' => WebhookEndpoint::factory(),
            'status' => fake()->randomElement(['pending', 'delivered', 'failed']),
            'attempt' => 1,
            'max_attempts' => 5,
            'last_response_status' => fake()->optional()->randomElement([200, 500, 502]),
            'last_latency_ms' => fake()->numberBetween(50, 5000),
            'idempotency_key_sent' => fake()->uuid(),
            'metadata' => [],
        ];
    }
}
