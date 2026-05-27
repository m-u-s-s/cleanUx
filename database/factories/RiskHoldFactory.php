<?php

namespace Database\Factories;

use App\Models\RiskEvaluation;
use App\Models\RiskHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiskHoldFactory extends Factory
{
    protected $model = RiskHold::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create()->id,
            'subject_type' => 'booking',
            'subject_id' => fake()->numberBetween(1, 1000),
            'risk_evaluation_id' => fn () => RiskEvaluation::factory()->create()->id,
            'status' => RiskHold::STATUS_ACTIVE,
            'reason' => fake()->sentence(),
            'expires_at' => now()->addHours(24),
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'metadata' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => RiskHold::STATUS_REVIEWED_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => RiskHold::STATUS_REVIEWED_REJECTED,
            'reviewed_at' => now(),
        ]);
    }
}
