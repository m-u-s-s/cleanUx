<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\RatingReport;

/**
 * Les signalements d’avis à modérer.
 *
 * La DÉCISION de modération passe par le module Avis : masquer un avis recalcule les agrégats
 * du prestataire et sa note publique. Poser un statut ici laisserait la note inchangée, et
 * l’avis masqué continuerait de compter.
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
}
