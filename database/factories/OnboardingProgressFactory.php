<?php

namespace Database\Factories;

use App\Models\OnboardingJourney;
use App\Models\OnboardingProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnboardingProgressFactory extends Factory
{
    protected $model = OnboardingProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create()->id,
            'journey_id' => fn () => OnboardingJourney::factory()->create()->id,
            'status' => OnboardingProgress::STATUS_NOT_STARTED,
            'started_at' => null,
            'completed_at' => null,
            'abandoned_at' => null,
            'current_step_code' => null,
            'percent_complete' => 0,
            'metadata' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => OnboardingProgress::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
            'percent_complete' => fake()->numberBetween(10, 90),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OnboardingProgress::STATUS_COMPLETED,
            'started_at' => now()->subDay(),
            'completed_at' => now(),
            'percent_complete' => 100,
        ]);
    }
}
