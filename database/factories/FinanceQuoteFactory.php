<?php

namespace Database\Factories;

use App\Models\FinanceQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinanceQuoteFactory extends Factory
{
    protected $model = FinanceQuote::class;

    public function definition(): array
    {
        $subtotal    = fake()->randomFloat(2, 50, 2000);
        $taxRate     = 21.00;
        $taxAmount   = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [
            'rendez_vous_id'         => null,
            'client_id'              => fn () => User::factory()->create()->id,
            'organization_account_id' => null,
            'quote_number'           => 'QUO-' . fake()->unique()->numerify('########'),
            'status'                 => 'draft',
            'currency'               => 'EUR',
            'subtotal'               => $subtotal,
            'tax_rate'               => $taxRate,
            'tax_amount'             => $taxAmount,
            'total_amount'           => $totalAmount,
            'issued_at'              => now(),
            'valid_until'            => now()->addDays(30),
            'accepted_at'            => null,
            'snapshot'               => null,
            'meta'                   => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);
    }
}
