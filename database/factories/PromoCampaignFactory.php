<?php

namespace Database\Factories;

use App\Models\PromoCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromoCampaignFactory extends Factory
{
    protected $model = PromoCampaign::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'description' => fake()->sentence(),
            'status' => PromoCampaign::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'budget_cap' => null,
            'total_discounted' => 0,
            'total_redemptions' => 0,
            'target_audience' => null,
            'metadata' => null,
            'created_by_user_id' => fn () => User::factory()->admin()->create()->id,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => PromoCampaign::STATUS_DRAFT]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => PromoCampaign::STATUS_ARCHIVED]);
    }
}
