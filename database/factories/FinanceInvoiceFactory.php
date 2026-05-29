<?php

namespace Database\Factories;

use App\Models\FinanceInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinanceInvoiceFactory extends Factory
{
    protected $model = FinanceInvoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 2000);
        $taxRate = 21.00;
        $taxAmount = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [
            'rendez_vous_id' => null,
            'finance_quote_id' => null,
            'client_id' => fn () => User::factory()->create()->id,
            'organization_account_id' => null,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('########'),
            'status' => 'issued',
            'currency' => 'EUR',
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'balance_due' => $totalAmount,
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
            'paid_at' => null,
            'snapshot' => null,
            'meta' => null,
            'billing_period_start' => null,
            'billing_period_end' => null,
            'invoice_type' => 'standard',
            'site_breakdown' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'balance_due' => 0,
            'paid_at' => now(),
        ]);
    }
}
