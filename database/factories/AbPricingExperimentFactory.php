<?php

namespace Database\Factories;

use App\Models\AbPricingExperiment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AbPricingExperimentFactory extends Factory
{
    protected $model = AbPricingExperiment::class;

    public function definition(): array
    {
        return [
            'code' => 'exp_' . Str::upper(Str::random(8)),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'service_codes' => null,
            'variants' => [
                ['label' => 'control', 'multiplier' => 1.0],
                ['label' => 'variant_a', 'multiplier' => 1.1],
            ],
            'traffic_allocation' => ['control' => 50, 'variant_a' => 50],
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(1),
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
