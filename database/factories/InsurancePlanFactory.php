<?php

namespace Database\Factories;

use App\Models\InsurancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsurancePlanFactory extends Factory
{
    protected $model = InsurancePlan::class;

    public function definition(): array
    {
        return [
            'code' => 'INS-'.fake()->unique()->bothify('??###'),
            'name' => fake()->randomElement(['Basic Protection', 'Premium Coverage', 'Full Shield']),
            'description' => fake()->sentence(),
            'trade_codes' => ['cleaning', 'painting'],
            'coverage_amount_cents' => fake()->randomElement([50000, 100000, 250000]),
            'premium_base_cents' => fake()->numberBetween(200, 1000),
            'premium_percent' => fake()->randomFloat(4, 0.01, 0.05),
            'min_premium_cents' => 200,
            'max_premium_cents' => 5000,
            'currency' => 'EUR',
            'is_active' => true,
            'valid_from' => now()->subMonths(1)->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'metadata' => [],
        ];
    }
}
