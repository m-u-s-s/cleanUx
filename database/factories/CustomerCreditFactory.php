<?php

namespace Database\Factories;

use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** ÉCRITE CONTRE UN SCHÉMA IMAGINAIRE, RÉÉCRITE CONTRE LE VRAI. */
class CustomerCreditFactory extends Factory
{
    protected $model = CustomerCredit::class;

    public function definition(): array
    {
        $montant = fake()->randomFloat(2, 5, 100);

        return [
            'client_id' => User::factory(),
            'rendez_vous_id' => null,
            'type' => fake()->randomElement(['referral_reward', 'goodwill', 'cancellation_refund', 'promo']),
            'amount' => $montant,
            'remaining_amount' => $montant,
            'status' => 'active',
            'reason' => fake()->sentence(),
            'notes' => null,
            'expires_at' => now()->addMonths(6),
        ];
    }

    /** Un avoir entièrement consommé. */
    public function consomme(): self
    {
        return $this->state(fn (array $attributs) => [
            'remaining_amount' => 0,
            'status' => 'consumed',
        ]);
    }

    /** Un avoir dont la date de validité est passée. */
    public function expire(): self
    {
        return $this->state(fn (array $attributs) => [
            'expires_at' => now()->subDay(),
            'status' => 'expired',
        ]);
    }
}
