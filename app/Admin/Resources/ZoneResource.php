<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ServiceZone;

/**
 * Les zones de service.
 *
 * Le périmètre postal et les règles de couverture ne s'éditent pas ici : ils engagent le
 * matching et la tarification, et se modifient depuis la page web qui montre leurs conséquences.
 *
 * @extends EloquentResource<ServiceZone>
 */
class ZoneResource extends EloquentResource
{
    public function key(): string
    {
        return 'zones';
    }

    protected function model(): string
    {
        return ServiceZone::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Zone'],
            'code' => ['Code'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'is_bookable' => ['Réservable', Column::TYPE_BOOL],
            'priority' => ['Priorité', Column::TYPE_NUMBER],
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
            'status' => ['Statut', 'status', [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'coverage_type' => 'Type de couverture',
            'minimum_notice_hours' => 'Préavis minimal (h)',
            'maximum_daily_jobs' => 'Missions max/jour',
            'notes' => 'Notes',
        ];
    }
}
