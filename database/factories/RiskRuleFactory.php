<?php

namespace Database\Factories;

use App\Models\RiskRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RiskRuleFactory extends Factory
{
    protected $model = RiskRule::class;

    public function definition(): array
    {
        return [
            'code' => 'rule_'.Str::lower(Str::random(8)),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'severity' => fake()->randomElement([
                RiskRule::SEVERITY_LOW,
                RiskRule::SEVERITY_MEDIUM,
                RiskRule::SEVERITY_HIGH,
                RiskRule::SEVERITY_CRITICAL,
            ]),
            'score_delta' => fake()->numberBetween(5, 50),
            'is_active' => true,
            'params' => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
