<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    public function definition(): array
    {
        return [
            'code' => 'PROMO'.Str::upper(Str::random(6)),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'discount_type' => fake()->randomElement(['percent', 'fixed_amount']),
            'discount_value' => fake()->randomFloat(2, 5, 30),
            'max_discount_amount' => fake()->randomFloat(2, 10, 50),
            'min_booking_amount' => fake()->randomFloat(2, 20, 100),
            'max_total_uses' => fake()->numberBetween(50, 500),
            'max_uses_per_user' => 1,
            'total_uses' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(3),
            'first_booking_only' => false,
            'stackable_with_credits' => true,
            'stackable_with_referral' => false,
            'audience_scope' => 'all',
            'status' => 'active',
            'source' => 'manual',
            'metadata' => [],
        ];
    }
}
