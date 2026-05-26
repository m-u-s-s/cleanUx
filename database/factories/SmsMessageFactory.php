<?php

namespace Database\Factories;

use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsMessageFactory extends Factory
{
    protected $model = SmsMessage::class;

    public function definition(): array
    {
        return [
            'provider' => 'mock',
            'to_phone' => fake()->e164PhoneNumber(),
            'from_phone' => '+32470000000',
            'body' => fake()->sentence(),
            'locale' => fake()->randomElement(['fr', 'nl', 'en']),
            'status' => fake()->randomElement(['queued', 'sent', 'delivered']),
            'attempts' => 1,
            'category' => fake()->randomElement(['transactional', 'verification', 'reminder']),
            'user_id' => User::factory(),
            'idempotency_key' => fake()->uuid(),
            'queued_at' => now(),
            'metadata' => [],
        ];
    }
}
