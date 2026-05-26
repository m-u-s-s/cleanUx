<?php

namespace Database\Factories;

use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PushNotificationFactory extends Factory
{
    protected $model = PushNotification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'mock',
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'data' => ['action' => 'open_booking', 'id' => fake()->numberBetween(1, 1000)],
            'locale' => 'fr',
            'category' => fake()->randomElement(['transactional', 'reminder']),
            'status' => 'queued',
            'attempts' => 0,
            'idempotency_key' => fake()->uuid(),
            'queued_at' => now(),
            'metadata' => [],
        ];
    }
}
