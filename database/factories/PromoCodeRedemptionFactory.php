<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromoCodeRedemptionFactory extends Factory
{
    protected $model = PromoCodeRedemption::class;

    public function definition(): array
    {
        return [
            'promo_code_id' => fn () => PromoCode::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'booking_id' => null,
            'discount_amount_cents' => fake()->numberBetween(200, 5000),
            'original_amount_cents' => fake()->numberBetween(5000, 30000),
            'final_amount_cents' => fake()->numberBetween(2000, 25000),
            'redeemed_at' => now(),
            'metadata' => null,
        ];
    }
}
