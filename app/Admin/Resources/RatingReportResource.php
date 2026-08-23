<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\RatingReport;

/**
 * Les signalements d’avis à modérer.
 *
 * @extends EloquentResource<RatingReport>
 */
class RatingReportResource extends EloquentResource
{
    public function key(): string
    {
        return 'ratings';
    }

    protected function model(): string
    {
        return RatingReport::class;
    }

    protected function columnSpec(): array
    {
        return [
            'reason' => ['Motif', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'details' => ['Détails'],
            'created_at' => ['Signalé le', Column::TYPE_DATE],
            'reviewed_at' => ['Traité le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['details'];
    }

    protected function searchLabel(): string
    {
        return 'Détails du signalement';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'upheld', 'label' => 'Retenu'],
                ['value' => 'dismissed', 'label' => 'Écarté'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'admin_note' => 'Note interne',
        ];
    }

    public function actions(): array
    {
        return [
            // Rejeter un signalement, c'est décider que l'avis RESTE.
            Action::make('dismiss', 'Rejeter le signalement', function (RatingReport $report) {
                $report->forceFill([
                    'status' => RatingReport::STATUS_DISMISSED,
                    'reviewed_by_user_id' => request()->user()?->id,
                    'reviewed_at' => now(),
                ])->save();

                return ['ok' => true];
            }),
        ];
    }
}
