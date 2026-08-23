<?php

namespace Database\Factories;

use App\Models\MultiTradeBundleItem;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * `MultiTradeBundleItemQuote` employait `HasFactory` sans qu'aucune fabrique existe : tout appel à
 * `::factory()` échouait sur « Class not found ». Ses deux sœurs — `MultiTradeBundle` et
 * `MultiTradeBundleItem` — en avaient une ; seule celle-ci manquait.
 *
 * Les clés suivent le schéma réel de `multi_trade_bundle_item_quotes`, vérifié colonne par colonne.
 */
class MultiTradeBundleItemQuoteFactory extends Factory
{
    protected $model = MultiTradeBundleItemQuote::class;

    public function definition(): array
    {
        return [
            'bundle_item_id' => fn () => MultiTradeBundleItem::factory()->create()->id,
            'provider_user_id' => fn () => User::factory()->employe()->create()->id,
            'status' => 'pending',
            'price_cents' => fake()->numberBetween(5000, 50000),
            'message' => fake()->sentence(),
            'valid_until' => now()->addDays(7),
            'submitted_at' => now(),
            'metadata' => null,
        ];
    }
}
