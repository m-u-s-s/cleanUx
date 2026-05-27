<?php

namespace Database\Factories;

use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyTier;
use Illuminate\Database\Eloquent\Factories\Factory;

class CancellationPolicyTierFactory extends Factory
{
    protected $model = CancellationPolicyTier::class;

    public function definition(): array
    {
        return [
            'policy_id'        => CancellationPolicy::factory(),
            'position'         => fake()->numberBetween(1, 5),
            'min_hours_before' => fake()->numberBetween(0, 12),
            'max_hours_before' => fake()->numberBetween(24, 72),
            'fee_percent'      => fake()->randomElement(['0.00', '25.00', '50.00', '100.00']),
            'fee_flat_cents'   => fake()->optional()->numberBetween(0, 5000),
            'description'      => fake()->sentence(),
            'metadata'         => [],
        ];
    }
}
