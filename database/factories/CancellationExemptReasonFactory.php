<?php

namespace Database\Factories;

use App\Models\CancellationExemptReason;
use App\Models\CancellationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class CancellationExemptReasonFactory extends Factory
{
    protected $model = CancellationExemptReason::class;

    public function definition(): array
    {
        return [
            'policy_id' => fn () => CancellationPolicy::factory()->create()->id,
            'reason_code' => 'reason_' . fake()->unique()->slug(2),
            'label' => fake()->words(3, true),
            'requires_proof' => fake()->boolean(),
            'max_per_user_per_30d' => fake()->numberBetween(1, 5),
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
