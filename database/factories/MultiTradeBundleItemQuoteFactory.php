<?php

namespace Database\Factories;

use App\Models\MultiTradeBundleItem;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MultiTradeBundleItemQuote> */
class MultiTradeBundleItemQuoteFactory extends Factory
{
    protected $model = MultiTradeBundleItemQuote::class;

    public function definition(): array
    {
        return [
            'bundle_item_id' => MultiTradeBundleItem::factory(),
            'provider_user_id' => User::factory(),
        ];
    }
}
