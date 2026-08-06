<?php

namespace Database\Factories;

use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderDraftItem> */
class OrderDraftItemFactory extends Factory
{
    protected $model = OrderDraftItem::class;

    public function definition(): array
    {
        return [
            'order_draft_id' => OrderDraft::factory(),
            'trade_id' => Trade::factory(),
        ];
    }
}
