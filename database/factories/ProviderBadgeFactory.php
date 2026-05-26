<?php

namespace Database\Factories;

use App\Models\ProviderBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderBadgeFactory extends Factory
{
    protected $model = ProviderBadge::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['star', 'shield-check', 'sparkles', 'bolt', 'fire']),
            'tier' => fake()->randomElement(['bronze', 'silver', 'gold', 'platinum']),
            'criterion_type' => fake()->randomElement(['missions_count', 'rating_avg', 'tips_received', 'tenure_days', 'loyalty_points', 'streak_5stars']),
            'threshold' => fake()->numberBetween(5, 100),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
