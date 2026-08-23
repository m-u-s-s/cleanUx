<?php

namespace Database\Factories;

use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ÉCRITE CONTRE UN SCHÉMA IMAGINAIRE, RÉÉCRITE CONTRE LE VRAI.
 *
 * Cette fabrique posait `user_id`, `currency`, `source_type`, `source_id` et `metadata` — cinq
 * clés dont AUCUNE n'existe sur `customer_credits`. Un `CustomerCredit::factory()->create()`
 * échouait donc, et personne ne s'en apercevait : la fabrique n'avait aucun appelant.
 *
 * La table porte `client_id`, `rendez_vous_id`, `type`, `amount`, `remaining_amount`, `status`,
 * `reason`, `notes` et `expires_at` — ce que déclare aussi `CustomerCredit::$fillable`.
 *
 * `remaining_amount` part égal à `amount` : un avoir naît entier. Les états ci-dessous couvrent
 * les deux autres cas plutôt que de les tirer au sort — un avoir à moitié consommé issu d'un
 * `random` rendrait les tests non reproductibles.
 */
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
