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
            // La table nomme le bénéficiaire `beneficiary_user_id`, le montant `amount` (unité
            // monétaire, pas centimes) et la date `granted_at`. `loyalty_points` n'existe pas :
            // un gain en points passe par `reward_type` et `amount`.
            'beneficiary_user_id' => fn () => User::factory()->create()->id,
            'role' => 'referrer',
            'reward_type' => fake()->randomElement(['promo_code', 'credit', 'loyalty_points']),
            'amount' => fake()->randomFloat(2, 5, 50),
            'currency' => 'EUR',
            'promo_code_id' => null,
            'status' => 'pending',
            'granted_at' => null,
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
