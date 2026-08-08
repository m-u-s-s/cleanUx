<?php

namespace Database\Factories;

use App\Models\QuestionStep;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionStep> */
class QuestionStepFactory extends Factory
{
    protected $model = QuestionStep::class;

    public function definition(): array
    {
        return [
            'trade_id' => Trade::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
