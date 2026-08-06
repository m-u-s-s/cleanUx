<?php

namespace Database\Factories;

use App\Models\Trade;
use App\Models\TradeFormRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TradeFormRevision> */
class TradeFormRevisionFactory extends Factory
{
    protected $model = TradeFormRevision::class;

    public function definition(): array
    {
        return [
            'trade_id' => Trade::factory(),
            'version' => fake()->numberBetween(1, 20),
            // Le schéma versionné du formulaire : ce qui permet à une réponse ancienne de rester
            // interprétable après reformulation des questions.
            'schema' => ['steps' => [], 'questions' => []],
        ];
    }
}
