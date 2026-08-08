<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionStep;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'trade_id' => Trade::factory(),
            'step_id' => QuestionStep::factory(),
            'code' => fake()->unique()->lexify('q_????????'),
            'label' => ucfirst(fake()->sentence(4)),
            // Le moteur de commande distingue ces types ; `text` est le plus neutre.
            'type' => fake()->randomElement(['text', 'number', 'select', 'boolean']),
            'is_required' => false,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
