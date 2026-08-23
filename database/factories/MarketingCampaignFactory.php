<?php

namespace Database\Factories;

use App\Models\PromoCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketingCampaignFactory extends Factory
{
    protected $model = PromoCampaign::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Campaign',
            'description' => fake()->sentence(),
            'status' => fake()->randomElement(['draft', 'active', 'paused', 'completed']),
            // `type` et `audience_segment_id` n'existent pas sur `promo_campaigns` : la table
            // porte `slug`, `target_audience`, `budget_cap` et les deux compteurs de suivi.
            'slug' => fake()->unique()->slug(3),
            'target_audience' => null,
            'budget_cap' => null,
            'total_discounted' => 0,
            'total_redemptions' => 0,
            'created_by_user_id' => User::factory(),
            'starts_at' => now(),
            'ends_at' => now()->addWeeks(2),
            'metadata' => [],
        ];
    }
}
