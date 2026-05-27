<?php

namespace Database\Factories;

use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionPlanV2Factory extends Factory
{
    protected $model = SubscriptionPlanV2::class;

    public function definition(): array
    {
        return [
            'code'                      => 'PLAN_' . Str::upper(Str::random(6)),
            'name'                      => fake()->words(2, true) . ' Plan',
            'description'               => fake()->sentence(),
            'trade_codes'               => null,
            'billing_period'            => fake()->randomElement(['monthly', 'quarterly', 'yearly']),
            'price_cents'               => fake()->numberBetween(999, 9999),
            'currency'                  => 'EUR',
            'included_units_per_cycle'  => fake()->numberBetween(5, 50),
            'included_unit_type'        => 'booking',
            'overage_unit_price_cents'  => fake()->numberBetween(100, 1000),
            'trial_days'                => fake()->randomElement([0, 7, 14, 30]),
            'features'                  => ['unlimited_zones', 'priority_support'],
            'is_active'                 => true,
            'version'                   => 1,
            'metadata'                  => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
