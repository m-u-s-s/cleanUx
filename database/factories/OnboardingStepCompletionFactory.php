<?php

namespace Database\Factories;

use App\Models\OnboardingProgress;
use App\Models\OnboardingStep;
use App\Models\OnboardingStepCompletion;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnboardingStepCompletionFactory extends Factory
{
    protected $model = OnboardingStepCompletion::class;

    public function definition(): array
    {
        return [
            'progress_id' => fn () => OnboardingProgress::factory()->create()->id,
            'step_id' => fn () => OnboardingStep::factory()->create()->id,
            'status' => OnboardingStepCompletion::STATUS_PENDING,
            'data' => null,
            'validator_payload' => null,
            'last_error' => null,
            'attempt_count' => 0,
            'completed_at' => null,
            'completed_by_user_id' => null,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OnboardingStepCompletion::STATUS_COMPLETED,
            'completed_at' => now(),
            'attempt_count' => 1,
        ]);
    }
}
