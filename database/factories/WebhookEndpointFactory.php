<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'code' => 'whep_'.Str::random(16),
            'name' => fake()->company().' Webhook',
            'owner_user_id' => User::factory(),
            'url' => fake()->url().'/webhooks',
            'secret' => Str::random(40),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout_seconds' => 30,
            'max_attempts' => 5,
            'is_active' => true,
            'is_suspended' => false,
            'consecutive_failures' => 0,
            'metadata' => [],
        ];
    }
}
