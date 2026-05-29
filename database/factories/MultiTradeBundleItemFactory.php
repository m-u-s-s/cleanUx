<?php

namespace Database\Factories;

use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

class MultiTradeBundleItemFactory extends Factory
{
    protected $model = MultiTradeBundleItem::class;

    public function definition(): array
    {
        return [
            'bundle_id' => fn () => MultiTradeBundle::factory()->create()->id,
            'trade_id' => fn () => Trade::factory()->create()->id,
            'label' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'duration_minutes' => fake()->numberBetween(60, 480),
            'estimated_price_cents' => fake()->numberBetween(5000, 50000),
            'quoted_price_cents' => null,
            'sequence_order' => fake()->numberBetween(1, 10),
            'depends_on_item_ids' => null,
            'assigned_provider_user_id' => null,
            'booking_id' => null,
            'status' => MultiTradeBundleItem::STATUS_DRAFT,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => MultiTradeBundleItem::STATUS_COMPLETED]);
    }
}
