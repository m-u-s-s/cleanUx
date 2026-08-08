<?php

namespace Database\Factories;

use App\Models\AsapDispatchRequest;
use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AsapDispatchRequest>
 *
 * Une demande immédiate : le rayon de recherche est en MÈTRES, d'où des valeurs de l'ordre de
 * plusieurs milliers et non quelques unités.
 */
class AsapDispatchRequestFactory extends Factory
{
    protected $model = AsapDispatchRequest::class;

    public function definition(): array
    {
        $brouillon = OrderDraft::factory();

        return [
            'order_draft_id' => $brouillon,
            'order_draft_item_id' => OrderDraftItem::factory(),
            'trade_id' => Trade::factory(),
            'radius_m' => fake()->numberBetween(2000, 25000),
        ];
    }
}
