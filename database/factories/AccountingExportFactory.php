<?php

namespace Database\Factories;

use App\Models\AccountingExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingExportFactory extends Factory
{
    protected $model = AccountingExport::class;

    public function definition(): array
    {
        return [
            'code' => AccountingExport::generateCode(),
            'format' => fake()->randomElement(['csv', 'fec', 'sage', 'quickbooks']),
            'period_year' => now()->year,
            'period_month' => now()->month,
            'status' => AccountingExport::STATUS_READY,
            'file_path' => 'exports/accounting/' . fake()->uuid() . '.csv',
            'file_size_bytes' => fake()->numberBetween(1000, 500000),
            'file_hash' => hash('sha256', fake()->text()),
            'row_count' => fake()->numberBetween(10, 1000),
            'generated_by_user_id' => null,
            'generated_at' => now(),
            'expires_at' => now()->addDays(30),
            'last_error' => null,
            'metadata' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => AccountingExport::STATUS_PENDING,
            'file_path' => null,
            'generated_at' => null,
        ]);
    }
}
