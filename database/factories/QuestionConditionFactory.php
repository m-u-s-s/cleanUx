<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionCondition> */
class QuestionConditionFactory extends Factory
{
    protected $model = QuestionCondition::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            // Une condition relie DEUX questions distinctes : la seconde gouverne l'affichage de
            // la première. Les faire pointer sur la même n'aurait aucun sens.
            'depends_on_question_id' => Question::factory(),
            'operator' => fake()->randomElement(['equals', 'not_equals', 'in', 'greater_than']),
            'value' => fake()->word(),
        ];
    }
}
