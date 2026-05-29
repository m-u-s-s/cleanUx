<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        $eventCodes = [
            'booking.created',
            'booking.completed',
            'booking.cancelled',
            'payment.succeeded',
            'provider.verified',
        ];

        return [
            'event_id' => WebhookEvent::generateEventId(),
            'event_code' => fake()->randomElement($eventCodes),
            'payload' => ['data' => ['id' => fake()->numberBetween(1, 500)]],
            'idempotency_key' => Str::uuid()->toString(),
            'source_type' => fake()->randomElement(['booking', 'payment', 'provider']),
            'source_id' => fake()->numberBetween(1, 500),
            'occurred_at' => now()->subSeconds(fake()->numberBetween(1, 3600)),
        ];
    }
}
