<?php

namespace Database\Factories;

use App\Models\LoyaltyReward;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoyaltyRewardFactory extends Factory
{
    protected $model = LoyaltyReward::class;

    public function definition(): array
    {
        return [
            'code' => 'RWD-' . fake()->unique()->bothify('??###'),
            'name' => fake()->randomElement(['10% Discount', 'Free Cleaning', 'Partner Voucher', 'Charity Donation']),
            'description' => fake()->sentence(),
            'reward_type' => fake()->randomElement(['discount_code', 'service_credit', 'physical_item', 'partner_voucher', 'charity_donation']),
            'category' => fake()->randomElement(['discount', 'service', 'gift', 'charity']),
            'points_cost' => fake()->randomElement([100, 250, 500, 1000]),
            'value_cents' => fake()->randomElement([500, 1000, 2500, 5000]),
            'currency' => 'EUR',
            'is_active' => true,
            'stock_initial' => fake()->numberBetween(50, 500),
            'stock_remaining' => fake()->numberBetween(10, 500),
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'metadata' => [],
        ];
    }
}
