<?php

namespace Database\Factories;

use App\Models\QualityChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

class QualityChecklistFactory extends Factory
{
    protected $model = QualityChecklist::class;

    public function definition(): array
    {
        return [
            'code' => 'QC-'.fake()->unique()->bothify('??###'),
            'name' => fake()->randomElement(['Pre-Cleaning Check', 'Post-Painting Inspection', 'Safety Audit']),
            'description' => fake()->sentence(),
            'trade_codes' => ['cleaning'],
            'phase' => fake()->randomElement(['pre', 'during', 'post', 'all']),
            'is_active' => true,
            'version' => 1,
            'metadata' => [],
        ];
    }
}
