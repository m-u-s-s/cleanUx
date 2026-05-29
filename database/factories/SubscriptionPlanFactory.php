<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Weekly', 'Bi-weekly', 'Monthly']);

        // The live subscription_plans table (2026_05_04 v2 marketplace schema) has
        // name/slug/is_active; slug is NOT NULL UNIQUE. The legacy
        // frequency_per_month/discount_rate columns do not exist on it, so we do not
        // write them here (they remain allowlisted drift on the dormant model).
        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
