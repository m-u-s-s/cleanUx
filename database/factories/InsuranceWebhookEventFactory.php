<?php

namespace Database\Factories;

use App\Models\InsuranceWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InsuranceWebhookEventFactory extends Factory
{
    protected $model = InsuranceWebhookEvent::class;

    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['mock', 'hiscox', 'wakam']),
            'external_event_id' => (string) Str::uuid(),
            'event_type' => fake()->randomElement(['claim.updated', 'policy.renewed', 'claim.approved']),
            'payload' => ['status' => 'received'],
            'status' => InsuranceWebhookEvent::STATUS_RECEIVED,
            'attempts' => 0,
            'last_error' => null,
            'received_at' => now(),
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => InsuranceWebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }
}
