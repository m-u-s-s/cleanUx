<?php

namespace Database\Factories;

use App\Models\OrderDraftItem;
use App\Models\OrderDraftMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderDraftMedia> */
class OrderDraftMediaFactory extends Factory
{
    protected $model = OrderDraftMedia::class;

    public function definition(): array
    {
        return [
            'order_draft_item_id' => OrderDraftItem::factory(),
            'path' => 'order-drafts/'.fake()->uuid().'.jpg',
        ];
    }
}
