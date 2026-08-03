<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\TripTrackingSession;

/**
 * Les sessions de suivi de trajet.
 *
 * LECTURE SEULE. Une session porte la preuve de présence sur le lieu d’intervention — code
 * confirmé, distance au point, verdict géographique. La corriger à la main effacerait justement
 * ce qu’elle sert à établir en cas de litige.
 *
 * @extends EloquentResource<TripTrackingSession>
 */
class TripTrackingResource extends EloquentResource
{
    public function key(): string
    {
        return 'trip-tracking';
    }

    protected function model(): string
    {
        return TripTrackingSession::class;
    }

    protected function columnSpec(): array
    {
        return [
            'code' => ['Référence'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'total_distance_m' => ['Distance (m)', Column::TYPE_NUMBER],
            'points_count' => ['Points', Column::TYPE_NUMBER],
            'started_at' => ['Démarrée le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['code'];
    }

    protected function searchLabel(): string
    {
        return 'Référence';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'started', 'label' => 'Démarrée'],
                ['value' => 'arrived', 'label' => 'Arrivé'],
                ['value' => 'in_mission', 'label' => 'En mission'],
                ['value' => 'ended', 'label' => 'Terminée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'presence_geo_verdict' => 'Verdict géographique',
            'presence_confirmed_at' => 'Présence confirmée le',
            'arrived_at' => 'Arrivé le',
            'ended_at' => 'Terminée le',
        ];
    }
}
