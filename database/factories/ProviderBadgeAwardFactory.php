<?php

namespace Database\Factories;

use App\Models\ProviderBadge;
use App\Models\ProviderBadgeAward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderBadgeAwardFactory extends Factory
{
    protected $model = ProviderBadgeAward::class;

    public function definition(): array
    {
        return [
            'provider_user_id' => User::factory(),
            'badge_id' => ProviderBadge::factory(),
            'value_at_award' => fake()->numberBetween(10, 500),
            'awarded_at' => now(),
            'metadata' => [],
        ];
    }
}
