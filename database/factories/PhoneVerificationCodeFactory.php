<?php

namespace Database\Factories;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneVerificationCodeFactory extends Factory
{
    protected $model = PhoneVerificationCode::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create()->id,
            'phone' => '+32' . fake()->numerify('4########'),
            'code_hash' => hash('sha256', fake()->numerify('######')),
            'attempts' => 0,
            'max_attempts' => 3,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'purpose' => 'phone_verification',
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinutes(5)]);
    }

    public function used(): static
    {
        return $this->state(fn () => ['used_at' => now()]);
    }
}
