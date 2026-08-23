<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\ServiceCatalog;
use App\Support\ActivityLogger;

/**
 * Le catalogue des prestations.
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

    public function formFields(): array
    {
        return [
            Field::make('code', 'Code')->rules(['required', 'string', 'max:50']),
            Field::make('name', 'Nom')->rules(['required', 'string', 'max:255']),
            Field::make('slug', 'Identifiant')->rules(['required', 'string', 'max:255']),
            Field::make('description', 'Description', Field::TYPE_TEXTAREA)->rules(['nullable', 'string', 'max:5000']),
            Field::make('service_type', 'Type')->rules(['required', 'string', 'max:50']),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('toggle-active', 'Activer / désactiver', function (ServiceCatalog $service) {
                $service->forceFill(['is_active' => ! $service->is_active])->save();
                ActivityLogger::log('service.toggled', $service, ['is_active' => $service->is_active]);

                return ['is_active' => (bool) $service->fresh()->is_active];
            }),
        ];
    }
}
