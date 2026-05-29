<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'referrer_user_id' => User::factory(),
            'referee_email' => fake()->safeEmail(),
            'referral_code' => 'REF'.strtoupper(fake()->bothify('????####')),
            'status' => 'invited',
            'referrer_reward_amount' => 10.00,
            'referee_reward_amount' => 10.00,
            'currency' => 'EUR',
            'source_channel' => fake()->randomElement(['email', 'whatsapp', 'link', 'sms']),
            'invited_at' => now(),
            'expires_at' => now()->addDays(30),
            'metadata' => [],
        ];
    }
}
