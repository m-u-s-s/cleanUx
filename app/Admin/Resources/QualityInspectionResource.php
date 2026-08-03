<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\MissionQualityInspection;

/**
 * Les inspections qualité des missions.
 *
 * Une inspection est VERSIONNÉE et signée : elle atteste d’un état constaté à un instant. La
 * rejouer depuis une liste produirait une attestation sans constat.
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
}
