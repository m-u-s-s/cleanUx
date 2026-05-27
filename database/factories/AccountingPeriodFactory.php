<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        return [
            'period_year' => now()->year,
            'period_month' => now()->month,
            'is_closed' => false,
            'opened_at' => now()->startOfMonth(),
            'closed_at' => null,
            'closed_by_user_id' => null,
            'total_debit_cents' => 0,
            'total_credit_cents' => 0,
            'entry_count' => 0,
            'totals_by_account' => [],
            'metadata' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'is_closed' => true,
            'closed_at' => now(),
            'total_debit_cents' => fake()->numberBetween(10000, 100000),
            'total_credit_cents' => fake()->numberBetween(10000, 100000),
        ]);
    }
}
