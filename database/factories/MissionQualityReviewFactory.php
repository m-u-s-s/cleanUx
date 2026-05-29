<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionQualityReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionQualityReviewFactory extends Factory
{
    protected $model = MissionQualityReview::class;

    public function definition(): array
    {
        return [
            'mission_id' => fn () => Mission::factory()->create()->id,
            'reviewer_user_id' => fn () => User::factory()->create()->id,
            'reviewer_role' => fake()->randomElement(['client', 'admin', 'team_lead']),
            'final_status' => fake()->randomElement(['approved', 'rejected', 'pending']),
            'score' => fake()->numberBetween(1, 10),
            'cleanliness_score' => fake()->numberBetween(1, 5),
            'punctuality_score' => fake()->numberBetween(1, 5),
            'behavior_score' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
            'reviewed_at' => now(),
            'meta' => null,
        ];
    }
}
