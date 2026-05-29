<?php

namespace Database\Factories;

use App\Models\RiskEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RiskEvaluationFactory extends Factory
{
    protected $model = RiskEvaluation::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->create()->id,
            'subject_type' => 'booking',
            'subject_id' => fake()->numberBetween(1, 1000),
            'context' => RiskEvaluation::CONTEXT_BOOKING_CREATE,
            'score' => fake()->numberBetween(0, 100),
            'decision' => RiskEvaluation::DECISION_ALLOW,
            'reason' => null,
            'triggered_rules' => [],
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_short' => 'Mozilla/5.0',
            'idempotency_key' => (string) Str::uuid(),
            'evaluated_at' => now(),
            'metadata' => null,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'decision' => RiskEvaluation::DECISION_BLOCK,
            'score' => fake()->numberBetween(70, 100),
        ]);
    }

    public function review(): static
    {
        return $this->state(fn () => [
            'decision' => RiskEvaluation::DECISION_REVIEW,
            'score' => fake()->numberBetween(40, 70),
        ]);
    }
}
