<?php

namespace Database\Factories;

use App\Models\AccountingEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingEntryFactory extends Factory
{
    protected $model = AccountingEntry::class;

    public function definition(): array
    {
        $isDebit = fake()->boolean();

        return [
            'entry_code' => 'ACC-'.fake()->unique()->numerify('########'),
            'batch_id' => fake()->uuid(),
            'posting_date' => fake()->date(),
            'journal_code' => fake()->randomElement(['VE', 'AC', 'BQ', 'OD']),
            'account_code' => fake()->randomElement(['411000', '512000', '706000', '401000']),
            'account_name' => fake()->randomElement(['Clients', 'Banque', 'Prestations de services', 'Fournisseurs']),
            'debit_cents' => $isDebit ? fake()->numberBetween(1000, 100000) : 0,
            'credit_cents' => $isDebit ? 0 : fake()->numberBetween(1000, 100000),
            'label' => fake()->sentence(4),
            'reference' => 'CUX-'.fake()->numerify('######'),
            'currency' => 'EUR',
            'exchange_rate' => 1.0,
            'metadata' => [],
        ];
    }
}
