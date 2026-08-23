<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\MissionQualityInspection;
use App\Services\Quality\QualityInspectionService;

/**
 * Les inspections qualité des missions.
 *
 * @extends EloquentResource<MissionQualityInspection>
 */
class QualityInspectionResource extends EloquentResource
{
    public function key(): string
    {
        return 'quality';
    }

    protected function model(): string
    {
        return MissionQualityInspection::class;
    }

    protected function columnSpec(): array
    {
        return [
            'phase' => ['Phase', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'score_calculated' => ['Score', Column::TYPE_NUMBER],
            'score_max' => ['Score max', Column::TYPE_NUMBER],
            'submitted_at' => ['Soumise le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['dispute_reason'];
    }

    protected function searchLabel(): string
    {
        return 'Motif de contestation';
    }

    protected function selectFilters(): array
    {
        return [
            'phase' => ['Phase', 'phase', [
                ['value' => 'before', 'label' => 'Avant'],
                ['value' => 'after', 'label' => 'Après'],
            ]],
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'submitted', 'label' => 'Soumise'],
                ['value' => 'validated', 'label' => 'Validée'],
                ['value' => 'disputed', 'label' => 'Contestée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'validated_at' => 'Validée le',
            'disputed_at' => 'Contestée le',
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('validate', 'Valider l’inspection', function (MissionQualityInspection $inspection) {
                app(QualityInspectionService::class)->validateByAdmin($inspection, request()->user());

                return ['ok' => true];
            }),

            Action::make('reject', 'Rejeter l’inspection', function (MissionQualityInspection $inspection, array $valeurs) {
                // Le motif est OBLIGATOIRE : un rejet sans raison est incontestable par le
                // prestataire, donc injuste, et il reviendra en litige.
                app(QualityInspectionService::class)->reject(
                    $inspection,
                    request()->user(),
                    (string) $valeurs['reason'],
                );

                return ['ok' => true];
            })->requires([
                Field::make('reason', 'Motif du rejet', Field::TYPE_TEXTAREA)
                    ->rules(['required', 'string', 'min:5', 'max:1000']),
            ]),
        ];
    }
}
