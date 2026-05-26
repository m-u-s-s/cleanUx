<?php

namespace Database\Factories;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoyaltyTransactionFactory extends Factory
{
    protected $model = LoyaltyTransaction::class;

    public function definition(): array
    {
        return [
            'loyalty_account_id' => LoyaltyAccount::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['earn_booking', 'earn_referral', 'earn_rating', 'redeem']),
            'direction' => fake()->randomElement(['credit', 'debit']),
            'points' => fake()->numberBetween(10, 500),
            'balance_after' => fake()->numberBetween(0, 5000),
            'reason' => fake()->sentence(),
            'idempotency_key' => fake()->uuid(),
            'occurred_at' => now(),
            'metadata' => [],
        ];
    }
}
