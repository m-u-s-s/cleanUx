<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\MissionBatch;

/**
 * Les lots de missions confiés aux équipes de terrain.
 *
 * @extends EloquentResource<MissionBatch>
 */
class MissionBatchResource extends EloquentResource
{
    public function key(): string
    {
        return 'orchestration';
    }

    protected function model(): string
    {
        return MissionBatch::class;
    }

    protected function columnSpec(): array
    {
        return [
            'reference' => ['Référence'],
            'name' => ['Lot'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'starts_on' => ['Début', Column::TYPE_DATE],
            'ends_on' => ['Fin', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['reference', 'name', 'notes'];
    }

    protected function searchLabel(): string
    {
        return 'Référence, nom ou notes';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'draft', 'label' => 'Brouillon'],
                ['value' => 'planned', 'label' => 'Planifié'],
                ['value' => 'running', 'label' => 'En cours'],
                ['value' => 'completed', 'label' => 'Terminé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'batch_type' => 'Type',
            'estimated_total_minutes' => 'Durée estimée (min)',
            'estimated_total_cost' => 'Coût estimé',
            'notes' => 'Notes',
        ];
    }
}
