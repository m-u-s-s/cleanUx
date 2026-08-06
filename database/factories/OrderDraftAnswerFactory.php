<?php

namespace Database\Factories;

use App\Models\OrderDraftAnswer;
use App\Models\OrderDraftItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDraftAnswer>
 *
 * `question_label_snapshot` est une PHOTOGRAPHIE du libellé au moment de la réponse : le
 * catalogue étant versionné, la question peut être reformulée plus tard sans que la réponse
 * déjà donnée en devienne incompréhensible.
 */
class OrderDraftAnswerFactory extends Factory
{
    protected $model = OrderDraftAnswer::class;

    public function definition(): array
    {
        $libelle = ucfirst(fake()->sentence(4));

        return [
            'order_draft_item_id' => OrderDraftItem::factory(),
            'question_code' => fake()->unique()->lexify('q_????????'),
            'question_label_snapshot' => $libelle,
            'value' => fake()->word(),
        ];
    }
}
