<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ExchangeRate;
use App\Services\Fx\FxService;

/**
 * Les taux de change appliqués. LECTURE SEULE.
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

    public function globalActions(): array
    {
        return [
            // Rafraîchir TOUS les taux.
            Action::make('refresh-all', 'Rafraîchir les taux', function (array $valeurs) {
                $inseres = app(FxService::class)->refreshAll();

                return ['inserted' => $inseres];
            }),
        ];
    }
}
