<?php

namespace Database\Factories;

use App\Models\ProviderQuote;
use App\Models\ProviderQuoteLine;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderQuoteLine>
 */
class ProviderQuoteLineFactory extends Factory
{
    protected $model = ProviderQuoteLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_quote_id' => ProviderQuote::factory(),
            'trade_id' => Trade::factory(),
            'label' => 'Nettoyage des parties communes',
            'quantity' => 1,
            'unit' => 'forfait',
            'unit_price_cents' => 25000,
            'total_cents' => 25000,
            'sort_order' => 0,
        ];
    }
}
