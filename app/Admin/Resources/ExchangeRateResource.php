<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ExchangeRate;

/**
 * Les taux de change appliqués.
 *
 * LECTURE SEULE. Les conversions déjà faites référencent le taux qui a servi ; le modifier après
 * coup ferait mentir des montants déjà facturés.
 *
 * @extends EloquentResource<ExchangeRate>
 */
class ExchangeRateResource extends EloquentResource
{
    public function key(): string
    {
        return 'fx';
    }

    protected function model(): string
    {
        return ExchangeRate::class;
    }

    protected function columnSpec(): array
    {
        return [
            'base_currency' => ['Devise de base', Column::TYPE_BADGE],
            'quote_currency' => ['Devise cible', Column::TYPE_BADGE],
            'rate' => ['Taux', Column::TYPE_NUMBER],
            'source' => ['Source'],
            'fetched_at' => ['Relevé le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['base_currency', 'quote_currency', 'source'];
    }

    protected function searchLabel(): string
    {
        return 'Devise ou source';
    }

    protected function detailSpec(): array
    {
        return [
            'valid_from' => 'Valide à partir du',
            'valid_until' => 'Valide jusqu’au',
        ];
    }
}
