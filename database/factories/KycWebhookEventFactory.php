<?php

namespace Database\Factories;

use App\Models\KycWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KycWebhookEventFactory extends Factory
{
    protected $model = KycWebhookEvent::class;

    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['mock', 'onfido', 'veriff']),
            'external_event_id' => (string) Str::uuid(),
            'event_type' => fake()->randomElement(['check.completed', 'applicant.updated']),
            'payload' => ['status' => 'received'],
            'status' => KycWebhookEvent::STATUS_RECEIVED,
            'attempts' => 0,
            'last_error' => null,
            'received_at' => now(),
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => KycWebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }
}
