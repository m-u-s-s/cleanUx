<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\NotificationPreference;

/**
 * La matrice canal × catégorie des préférences de notification.
 *
 * LECTURE SEULE, et ce n’est pas une limitation. Certaines catégories sont forcées à l’envoi
 * pour raison légale ; les basculer depuis une liste contournerait ce verrou sans que rien ne
 * s’y oppose. Le module dédié connaît, lui, ce qui est modifiable.
 *
 * @extends EloquentResource<NotificationPreference>
 */
class NotificationPreferenceResource extends EloquentResource
{
    public function key(): string
    {
        return 'notification-preferences';
    }

    protected function model(): string
    {
        return NotificationPreference::class;
    }

    protected function columnSpec(): array
    {
        return [
            'channel' => ['Canal', Column::TYPE_BADGE],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'is_allowed' => ['Autorisé', Column::TYPE_BOOL],
            'version' => ['Version', Column::TYPE_NUMBER],
            'last_changed_at' => ['Modifié le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['channel', 'category'];
    }

    protected function searchLabel(): string
    {
        return 'Canal ou catégorie';
    }

    protected function detailSpec(): array
    {
        return [
            'source' => 'Origine du choix',
        ];
    }
}
