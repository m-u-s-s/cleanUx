<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\RiskEvaluation;

/**
 * Les évaluations de risque et leurs décisions. LECTURE SEULE ici.
 *
 * @extends EloquentResource<RiskEvaluation>
 */
class RiskEvaluationResource extends EloquentResource
{
    public function key(): string
    {
        return 'risk';
    }

    protected function model(): string
    {
        return RiskEvaluation::class;
    }

    protected function columnSpec(): array
    {
        return [
            'decision' => ['Décision', Column::TYPE_BADGE],
            'score' => ['Score', Column::TYPE_NUMBER],
            'reason' => ['Motif'],
            'subject_type' => ['Sujet'],
            'evaluated_at' => ['Évaluée le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['reason', 'subject_type'];
    }

    protected function searchLabel(): string
    {
        return 'Motif ou type de sujet';
    }

    protected function selectFilters(): array
    {
        return [
            'decision' => ['Décision', 'decision', [
                ['value' => 'allow', 'label' => 'Autorisé'],
                ['value' => 'review', 'label' => 'À revoir'],
                ['value' => 'deny', 'label' => 'Refusé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'context' => 'Contexte',
            'triggered_rules' => 'Règles déclenchées',
        ];
    }
}
