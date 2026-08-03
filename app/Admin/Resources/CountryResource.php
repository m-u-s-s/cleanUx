<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Country;

/**
 * Les pays ouverts à l’exploitation.
 *
 * `booking_enabled` coupe la prise de commande dans tout un pays : la bascule est annoncee comme
 * destructive parce qu'elle l’est — plus aucun client de ce pays ne peut commander.
 *
 * @extends EloquentResource<Country>
 */
class CountryResource extends EloquentResource
{
    public function key(): string
    {
        return 'countries';
    }

    protected function model(): string
    {
        return Country::class;
    }

    protected function columnSpec(): array
    {
        return [
            'iso_code' => ['Code ISO'],
            'name' => ['Pays'],
            'currency_code' => ['Devise', Column::TYPE_BADGE],
            'is_active' => ['Actif', Column::TYPE_BOOL],
            'booking_enabled' => ['Commande ouverte', Column::TYPE_BOOL],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'iso_code', 'official_name'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou code ISO';
    }

    protected function selectFilters(): array
    {
        return [
            'market_stage' => ['Stade', 'market_stage', [
                ['value' => 'pilot', 'label' => 'Pilote'],
                ['value' => 'live', 'label' => 'Ouvert'],
                ['value' => 'paused', 'label' => 'Suspendu'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'default_locale' => 'Langue par défaut',
            'phone_code' => 'Indicatif',
            'timezone' => 'Fuseau',
        ];
    }
}
