<?php

namespace Database\Factories;

use App\Models\EmailWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EmailWebhookEventFactory extends Factory
{
    protected $model = EmailWebhookEvent::class;

    public function definition(): array
    {
        return [
            'provider' => 'mailgun',
            'provider_event_id' => (string) Str::uuid(),
            'provider_message_id' => 'msg_'.Str::random(16),
            'email_message_id' => null,
            'event_type' => fake()->randomElement(['delivered', 'opened', 'clicked', 'bounced']),
            'occurred_at' => now(),
            'payload' => ['event' => 'delivered'],
        ];
    }
}
