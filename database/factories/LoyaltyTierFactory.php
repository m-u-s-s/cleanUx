<?php

namespace Database\Factories;

use App\Models\LoyaltyTier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LoyaltyTierFactory extends Factory
{
    protected $model = LoyaltyTier::class;

    private static int $rankCounter = 0;

    public function definition(): array
    {
        self::$rankCounter++;

        $slugs = ['bronze', 'silver', 'gold', 'platinum'];
        $slug  = fake()->randomElement($slugs) . '_' . Str::random(4);

        return [
            'slug'              => $slug,
            'name'              => ucfirst($slug),
            'min_period_points' => fake()->numberBetween(0, 10000),
            'rank'              => self::$rankCounter,
            'color'             => fake()->hexColor(),
            'icon'              => 'star',
            'discount_percent'  => fake()->randomElement(['0.00', '5.00', '10.00', '15.00']),
            'priority_dispatch' => fake()->boolean(),
            'vip_support'       => fake()->boolean(),
            'benefits'          => ['free_cancellation', 'priority_support'],
            'is_active'         => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
