<?php

namespace Database\Factories;

use App\Models\SmsWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SmsWebhookEventFactory extends Factory
{
    protected $model = SmsWebhookEvent::class;

    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['mock', 'twilio']),
            'external_event_id' => (string) Str::uuid(),
            'event_type' => fake()->randomElement(['delivery_receipt', 'status_update', 'inbound']),
            'payload' => ['status' => 'delivered'],
            'status' => SmsWebhookEvent::STATUS_RECEIVED,
            'attempts' => 0,
            'last_error' => null,
            'received_at' => now(),
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => SmsWebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }
}
