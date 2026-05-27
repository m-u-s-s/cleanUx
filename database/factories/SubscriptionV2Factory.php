<?php

namespace Database\Factories;

use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionV2Factory extends Factory
{
    protected $model = SubscriptionV2::class;

    public function definition(): array
    {
        $start = now()->subMonth()->startOfMonth();

        return [
            'code'                       => SubscriptionV2::generateCode(),
            'plan_id'                    => SubscriptionPlanV2::factory(),
            'user_id'                    => User::factory(),
            'provider_user_id'           => null,
            'status'                     => SubscriptionV2::STATUS_ACTIVE,
            'started_at'                 => $start,
            'trial_ends_at'              => null,
            'current_cycle_start'        => $start,
            'current_cycle_end'          => $start->copy()->addMonth()->subSecond(),
            'next_billing_at'            => $start->copy()->addMonth(),
            'paused_at'                  => null,
            'cancelled_at'               => null,
            'ends_at'                    => null,
            'cancel_at_period_end'       => false,
            'billing_currency'           => 'EUR',
            'billing_cycle_count'        => 1,
            'total_billed_cents'         => fake()->numberBetween(1000, 9000),
            'consecutive_failed_charges' => 0,
            'stripe_subscription_id'     => null,
            'metadata'                   => [],
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'       => SubscriptionV2::STATUS_CANCELLED,
            'cancelled_at' => now()->subDay(),
        ]);
    }
}
