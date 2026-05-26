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
            'name' => fake()->words(3, true) . ' Campaign',
            'description' => fake()->sentence(),
            'status' => fake()->randomElement(['draft', 'active', 'paused', 'completed']),
            'type' => fake()->randomElement(['promo_blast', 'drip', 'one_shot']),
            'audience_segment_id' => null,
            'created_by_user_id' => User::factory(),
            'starts_at' => now(),
            'ends_at' => now()->addWeeks(2),
            'metadata' => [],
        ];
    }
}
