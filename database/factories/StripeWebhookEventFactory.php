<?php

namespace Database\Factories;

use App\Models\StripeWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class StripeWebhookEventFactory extends Factory
{
    protected $model = StripeWebhookEvent::class;

    public function definition(): array
    {
        return [
            'stripe_event_id' => 'evt_'.fake()->md5(),
            'type' => fake()->randomElement([
                'payment_intent.succeeded',
                'payment_intent.payment_failed',
                'charge.refunded',
                'account.updated',
            ]),
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
            'payload' => ['id' => 'evt_'.fake()->md5(), 'type' => 'payment_intent.succeeded', 'data' => ['object' => []]],
            'result' => ['handled' => true],
            'attempts' => 1,
            'max_attempts' => 5,
            'last_error' => null,
            'received_at' => now()->subMinutes(5),
            'first_attempted_at' => now()->subMinutes(4),
            'processed_at' => now()->subMinutes(3),
            'next_retry_at' => null,
            'account_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'processed_at' => null,
            'first_attempted_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => StripeWebhookEvent::STATUS_FAILED,
            'attempts' => 3,
            'last_error' => 'Handler threw exception',
            'processed_at' => null,
            'next_retry_at' => now()->addMinutes(30),
        ]);
    }
}
