<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\CountryOperationalSetting;

/**
 * Les réglages d’exploitation par pays.
 *
 * Ces bascules coupent des pans entiers d’activité dans un pays : la commande, les missions, la
 * facturation. Elles se règlent sur la page dédiée, qui montre ce qui tombe avec chacune.
 *
 * @extends EloquentResource<CountryOperationalSetting>
 */
class CountrySettingResource extends EloquentResource
{
    public function key(): string
    {
        return 'international';
    }

    protected function model(): string
    {
        return CountryOperationalSetting::class;
    }

    protected function columnSpec(): array
    {
        return [
            'booking_enabled' => ['Commande', Column::TYPE_BOOL],
            'mission_enabled' => ['Missions', Column::TYPE_BOOL],
            'billing_enabled' => ['Facturation', Column::TYPE_BOOL],
            'readiness_stage' => ['Stade', Column::TYPE_BADGE],
            'currency_code' => ['Devise', Column::TYPE_BADGE],
        ];
    }

    protected function searchable(): array
    {
        return ['currency_code', 'timezone'];
    }

    protected function searchLabel(): string
    {
        return 'Devise ou fuseau';
    }

    protected function detailSpec(): array
    {
        return [
            'default_tax_rate' => 'TVA par défaut',
            'timezone' => 'Fuseau',
            'default_distance_unit' => 'Unité de distance',
            'partner_network_enabled' => 'Réseau partenaire',
        ];
    }
}
