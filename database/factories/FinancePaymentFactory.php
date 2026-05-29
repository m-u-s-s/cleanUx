<?php

namespace Database\Factories;

use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancePaymentFactory extends Factory
{
    protected $model = FinancePayment::class;

    public function definition(): array
    {
        return [
            'finance_invoice_id' => fn () => FinanceInvoice::factory()->create()->id,
            'payment_reference' => 'PAY-'.fake()->unique()->numerify('########'),
            'provider' => fake()->randomElement(['stripe', 'bank_transfer', 'cash']),
            'method' => fake()->randomElement(['card', 'bank_transfer', 'cash']),
            'status' => 'paid',
            'amount' => fake()->randomFloat(2, 50, 2000),
            'paid_at' => now(),
            'external_reference' => null,
            'notes' => null,
            'meta' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }
}
