<?php

namespace Database\Factories;

use App\Models\LoyaltyAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoyaltyAccountFactory extends Factory
{
    protected $model = LoyaltyAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lifetime_points' => fake()->numberBetween(0, 5000),
            'period_points' => fake()->numberBetween(0, 1000),
            'redeemable_points' => fake()->numberBetween(0, 1000),
            'tier_started_at' => now()->subMonths(3),
            'last_activity_at' => now(),
            'metadata' => [],
        ];
    }
}
