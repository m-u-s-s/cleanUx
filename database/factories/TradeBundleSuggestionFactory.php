<?php

namespace Database\Factories;

use App\Models\Trade;
use App\Models\TradeBundleSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TradeBundleSuggestion> */
class TradeBundleSuggestionFactory extends Factory
{
    protected $model = TradeBundleSuggestion::class;

    public function definition(): array
    {
        return [
            'trade_id' => Trade::factory(),
            // Deux métiers DISTINCTS : suggérer un métier à lui-même n'aurait pas de sens.
            'suggested_trade_id' => Trade::factory(),
        ];
    }
}
