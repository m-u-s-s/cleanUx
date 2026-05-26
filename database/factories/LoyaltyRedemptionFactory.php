<?php

namespace Database\Factories;

use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LoyaltyRedemptionFactory extends Factory
{
    protected $model = LoyaltyRedemption::class;

    public function definition(): array
    {
        return [
            'code' => 'RDM-' . Str::upper(Str::random(10)),
            'user_id' => User::factory(),
            'reward_id' => LoyaltyReward::factory(),
            'points_spent' => fake()->randomElement([100, 250, 500]),
            'status' => 'pending',
            'delivery_method' => fake()->randomElement(['email', 'in_app', 'postal']),
            'voucher_code' => fake()->optional()->bothify('VOUCH-####-????'),
            'metadata' => [],
        ];
    }
}
