<?php

namespace Database\Factories;

use App\Models\OrderDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderDraft> */
class OrderDraftFactory extends Factory
{
    protected $model = OrderDraft::class;

    public function definition(): array
    {
        return [
            // La référence est la seule colonne obligatoire, et elle doit rester unique.
            'reference' => 'DRAFT-'.fake()->unique()->numerify('########'),
        ];
    }
}
