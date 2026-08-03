<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ServiceCatalog;

/**
 * Le catalogue des prestations.
 *
 * Les OPTIONS et le questionnaire de commande ne s’éditent pas ici : ce sont des structures
 * imbriquées, et les aplatir dans un formulaire ferait perdre ce qui dépend de quoi.
 *
 * @extends EloquentResource<ServiceCatalog>
 */
class ServiceCatalogResource extends EloquentResource
{
    public function key(): string
    {
        return 'services';
    }

    protected function model(): string
    {
        return ServiceCatalog::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Prestation'],
            'code' => ['Code'],
            'base_price' => ['Prix de base', Column::TYPE_MONEY],
            'billing_unit' => ['Unité', Column::TYPE_BADGE],
            'is_active' => ['Active', Column::TYPE_BOOL],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'code', 'slug', 'description'];
    }

    protected function searchLabel(): string
    {
        return 'Nom, code ou description';
    }

    protected function selectFilters(): array
    {
        return [
            'service_type' => ['Type', 'service_type', [
                ['value' => 'standard', 'label' => 'Standard'],
                ['value' => 'premium', 'label' => 'Premium'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'default_duration_minutes' => 'Durée par défaut (min)',
            'vat_rate' => 'TVA',
            'requires_quote' => 'Sur devis',
            'requires_site_visit' => 'Visite préalable',
        ];
    }
}
