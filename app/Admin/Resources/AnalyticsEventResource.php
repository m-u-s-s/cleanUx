<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\AnalyticsEvent;

/**
 * Le journal des événements produit.
 *
 * @extends EloquentResource<AnalyticsEvent>
 */
class AnalyticsEventResource extends EloquentResource
{
    public function key(): string
    {
        return 'analytics-v2';
    }

    protected function model(): string
    {
        return AnalyticsEvent::class;
    }

    protected function columnSpec(): array
    {
        return [
            'event_name' => ['Événement'],
            'event_category' => ['Catégorie', Column::TYPE_BADGE],
            'platform' => ['Plateforme'],
            'occurred_at' => ['Survenu le', Column::TYPE_DATETIME],
            'created_at' => ['Enregistré le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['event_name', 'event_category'];
    }

    protected function searchLabel(): string
    {
        return 'Nom ou catégorie';
    }

    protected function detailSpec(): array
    {
        return [
            'source' => 'Source',
            'locale' => 'Langue',
            'country_code' => 'Pays',
            'url' => 'URL',
        ];
    }
}
