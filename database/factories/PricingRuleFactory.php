<?php

namespace Database\Factories;

use App\Models\PricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    public function definition(): array
    {
        return [
            'code' => 'PR-' . fake()->unique()->bothify('??###'),
            'name' => fake()->randomElement(['Weekend Surcharge', 'Evening Discount', 'Bulk Rate']),
            'description' => fake()->sentence(),
            'service_code' => fake()->optional()->slug(1),
            'trade_code' => fake()->optional()->randomElement(['cleaning', 'painting', 'gardening']),
            'priority' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'applies_when' => ['day_of_week' => ['saturday', 'sunday']],
            'adjustments' => [['kind' => 'percent', 'value' => 15, 'label' => 'Weekend']],
            'valid_from' => now()->toDateString(),
            'metadata' => [],
        ];
    }
}
