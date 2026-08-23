<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\CountryOperationalSetting;

/**
 * Les réglages d’exploitation par pays.
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

    public function formFields(): array
    {
        return [
            Field::select('readiness_stage', 'Étape d’ouverture', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'catalog_only', 'label' => 'Catalogue seul'],
                ['value' => 'booking_enabled', 'label' => 'Réservations ouvertes'],
                ['value' => 'mission_enabled', 'label' => 'Missions ouvertes'],
                ['value' => 'billing_enabled', 'label' => 'Facturation ouverte'],
                ['value' => 'ready_for_launch', 'label' => 'Prêt au lancement'],
            ])->rules(['required', 'in:draft,catalog_only,booking_enabled,mission_enabled,billing_enabled,ready_for_launch']),
            Field::make('currency_symbol', 'Symbole monétaire')->rules(['required', 'string', 'max:10']),
            Field::make('date_format', 'Format de date')->rules(['required', 'string', 'max:30']),
            Field::make('time_format', 'Format d’heure')->rules(['required', 'string', 'max:30']),
            Field::make('address_format', 'Format d’adresse')->rules(['required', 'string', 'max:100']),
            Field::make('default_tax_rate', 'Taux de TVA par défaut', Field::TYPE_NUMBER)
                ->rules(['nullable', 'numeric', 'min:0', 'max:100']),
        ];
    }
}
