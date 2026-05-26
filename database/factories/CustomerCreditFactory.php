<?php

namespace Database\Factories;

use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerCreditFactory extends Factory
{
    protected $model = CustomerCredit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 5, 100),
            'currency' => 'EUR',
            'reason' => fake()->randomElement(['referral_reward', 'goodwill', 'cancellation_refund', 'promo']),
            'source_type' => fake()->optional()->randomElement(['App\\Models\\Referral', 'App\\Models\\PromoCode']),
            'source_id' => fake()->optional()->numberBetween(1, 100),
            'expires_at' => now()->addMonths(6),
            'metadata' => [],
        ];
    }
}
