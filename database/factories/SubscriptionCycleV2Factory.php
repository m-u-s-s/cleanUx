<?php

namespace Database\Factories;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionCycleV2Factory extends Factory
{
    protected $model = SubscriptionCycleV2::class;

    public function definition(): array
    {
        $start = now()->subMonth()->startOfMonth();
        $end = now()->subMonth()->endOfMonth();
        $amount = fake()->numberBetween(1000, 9900);

        return [
            'subscription_id' => SubscriptionV2::factory(),
            'cycle_number' => fake()->numberBetween(1, 12),
            'period_start' => $start,
            'period_end' => $end,
            'planned_amount_cents' => $amount,
            'billed_amount_cents' => $amount,
            'used_units' => fake()->numberBetween(0, 10),
            'billing_status' => SubscriptionCycleV2::STATUS_PAID,
            'billed_at' => $start->copy()->addDay(),
            'invoice_id' => null,
            'billing_raw' => ['payment_intent' => 'pi_test_'.fake()->md5()],
            'last_error' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'billing_status' => SubscriptionCycleV2::STATUS_PENDING,
            'billed_at' => null,
            'billed_amount_cents' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'billing_status' => SubscriptionCycleV2::STATUS_FAILED,
            'last_error' => 'card_declined',
        ]);
    }
}
