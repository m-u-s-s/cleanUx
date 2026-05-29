<?php

namespace Database\Factories;

use App\Models\StripeReconciliationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class StripeReconciliationRunFactory extends Factory
{
    protected $model = StripeReconciliationRun::class;

    public function definition(): array
    {
        $start = now()->subMonth()->startOfMonth();
        $end = now()->subMonth()->endOfMonth();

        return [
            'scope' => StripeReconciliationRun::SCOPE_PAYMENT_INTENTS,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => StripeReconciliationRun::STATUS_COMPLETED,
            'items_checked' => fake()->numberBetween(10, 200),
            'mismatches_found' => 0,
            'auto_fixed' => 0,
            'requires_attention' => 0,
            'summary' => [],
            'mismatches' => [],
            'error_message' => null,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'triggered_by_user_id' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => StripeReconciliationRun::STATUS_FAILED,
            'error_message' => 'Connection timeout',
            'completed_at' => now(),
        ]);
    }
}
