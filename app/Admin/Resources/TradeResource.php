<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Trade;

/**
 * Les métiers de la plateforme.
 *
 * La GRILLE TARIFAIRE par zone et le questionnaire de commande ne s'éditent pas ici : ce sont
 * deux dimensions et un arbre, que le rendu générique ne sait pas montrer sans mentir sur leur
 * structure. Ils restent sur leurs pages dédiées.
 *
 * @extends EloquentResource<Trade>
 */
class TradeResource extends EloquentResource
{
    public function key(): string
    {
        return 'trades';
    }

    protected function model(): string
    {
        return Trade::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Métier'],
            'code' => ['Code'],
            'base_price_cents' => ['Prix de base (cents)', Column::TYPE_NUMBER],
            'is_active' => ['Actif', Column::TYPE_BOOL],
            'sort_order' => ['Ordre', Column::TYPE_NUMBER],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'slug'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou code';
    }

    protected function selectFilters(): array
    {
        return [
            'pricing_unit' => ['Unité de prix', 'pricing_unit', [
                ['value' => 'hour', 'label' => 'Heure'],
                ['value' => 'm2', 'label' => 'Mètre carré'],
                ['value' => 'fixed', 'label' => 'Forfait'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'short_description' => 'Description',
            'estimated_duration_min' => 'Durée estimée (min)',
            'requires_certification' => 'Certification requise',
            'requires_insurance_proof' => 'Assurance requise',
        ];
    }
}
