<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionOption> */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition(): array
    {
        $libelle = ucfirst(fake()->words(2, true));

        return [
            'question_id' => Question::factory(),
            'label' => $libelle,
            'value' => str($libelle)->slug()->toString(),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
