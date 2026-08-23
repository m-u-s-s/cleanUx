<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Mission;

/**
 * Les missions exécutées sur le terrain. NI RÉASSIGNATION NI CHANGEMENT DE STATUT ICI.
 *
 * @extends EloquentResource<Mission>
 */
class MissionResource extends EloquentResource
{
    public function key(): string
    {
        return 'missions';
    }

    protected function model(): string
    {
        return Mission::class;
    }

    protected function columnSpec(): array
    {
        return [
            'status' => ['Statut', Column::TYPE_BADGE],
            'planned_start_at' => ['Prévue le', Column::TYPE_DATETIME],
            'actual_start_at' => ['Démarrée le', Column::TYPE_DATETIME],
            'actual_end_at' => ['Terminée le', Column::TYPE_DATETIME],
            'client_price' => ['Prix client', Column::TYPE_MONEY],
        ];
    }

    protected function searchable(): array
    {
        return ['notes'];
    }

    protected function searchLabel(): string
    {
        return 'Notes';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'planned', 'label' => 'Planifiée'],
                ['value' => 'assigned', 'label' => 'Assignée'],
                ['value' => 'en_route', 'label' => 'En route'],
                ['value' => 'arrived', 'label' => 'Arrivé'],
                ['value' => 'started', 'label' => 'Démarrée'],
                ['value' => 'completed', 'label' => 'Terminée'],
                ['value' => 'cancelled', 'label' => 'Annulée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'quality_score' => 'Score qualité',
            'actual_duration_minutes' => 'Durée réelle (min)',
            'end_geo_verdict' => 'Verdict géographique',
            'notes' => 'Notes',
        ];
    }
}
