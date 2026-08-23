<?php

namespace Database\Factories;

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
            // NI LES NOMS NI L'UNITÉ N'ÉTAIENT LES BONS.
            'status' => 'applied',
            'currency' => 'EUR',
            'booking_amount_before' => $avant = fake()->randomFloat(2, 50, 300),
            'discount_amount' => $remise = fake()->randomFloat(2, 2, min(50, $avant)),
            'booking_amount_after' => round($avant - $remise, 2),
            'redeemed_at' => now(),
            'metadata' => null,
        ];
    }
}
