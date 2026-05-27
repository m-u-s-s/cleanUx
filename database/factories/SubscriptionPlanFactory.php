<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->randomElement(['Weekly', 'Bi-weekly', 'Monthly']),
            'frequency_per_month' => fake()->randomElement([4, 2, 1]),
            'discount_rate'       => fake()->randomFloat(2, 0, 20),
            'is_active'           => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
