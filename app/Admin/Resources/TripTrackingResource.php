<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\TripTrackingSession;
use App\Services\TripTracking\TripTrackingService;

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

    public function actions(): array
    {
        return [
            Action::make('cancel-session', 'Annuler la session', function (TripTrackingSession $session) {
                // Le motif est celui du web, mot pour mot : il sert à distinguer une annulation
                // administrative d'un abandon du prestataire dans les statistiques.
                app(TripTrackingService::class)->cancelSession($session, 'admin_manual');

                return ['ok' => true];
            })->destructive('La session de suivi sera annulée.'),
        ];
    }
}
