<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralRewardFactory extends Factory
{
    protected $model = ReferralReward::class;

    public function definition(): array
    {
        return [
            'referral_id' => fn () => Referral::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'reward_type' => fake()->randomElement(['promo_code', 'credit', 'loyalty_points']),
            'amount_cents' => fake()->numberBetween(500, 5000),
            'promo_code_id' => null,
            'loyalty_points' => null,
            'status' => 'pending',
            'issued_at' => null,
            'metadata' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn () => [
            'status' => 'issued',
            'issued_at' => now(),
        ]);
    }
}
