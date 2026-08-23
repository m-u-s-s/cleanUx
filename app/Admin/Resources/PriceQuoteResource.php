<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\PriceQuote;

/**
 * Le registre des devis calculés. LECTURE SEULE.
 *
 * @extends EloquentResource<PriceQuote>
 */
class PriceQuoteResource extends EloquentResource
{
    public function key(): string
    {
        return 'pricing';
    }

    protected function model(): string
    {
        return PriceQuote::class;
    }

    protected function columnSpec(): array
    {
        return [
            'service_code' => ['Prestation'],
            'trade_code' => ['Métier'],
            'computed_price_cents' => ['Prix calculé (cents)', Column::TYPE_NUMBER],
            'variant_label' => ['Variante', Column::TYPE_BADGE],
            'quoted_at' => ['Calculé le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['service_code', 'trade_code', 'variant_label'];
    }

    protected function searchLabel(): string
    {
        return 'Prestation, métier ou variante';
    }

    protected function detailSpec(): array
    {
        return [
            'base_price_cents' => 'Prix de base (cents)',
            'applied_rules' => 'Règles appliquées',
            'currency' => 'Devise',
        ];
    }
}
