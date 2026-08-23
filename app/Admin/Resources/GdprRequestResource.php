<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\GdprDataRequest;
use App\Services\Gdpr\RetentionPolicyService;

/**
 * Les demandes RGPD — export et droit à l’oubli. AUCUNE EXÉCUTION DEPUIS LA CONSOLE.
 *
 * @extends EloquentResource<GdprDataRequest>
 */
class GdprRequestResource extends EloquentResource
{
    public function key(): string
    {
        return 'gdpr';
    }

    protected function model(): string
    {
        return GdprDataRequest::class;
    }

    protected function columnSpec(): array
    {
        return [
            'reference' => ['Référence'],
            'type' => ['Type', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'requested_at' => ['Demandée le', Column::TYPE_DATETIME],
            'grace_period_ends_at' => ['Fin du délai', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['reference', 'reason'];
    }

    protected function searchLabel(): string
    {
        return 'Référence ou motif';
    }

    protected function selectFilters(): array
    {
        return [
            'type' => ['Type', 'type', [
                ['value' => 'export', 'label' => 'Export'],
                ['value' => 'deletion', 'label' => 'Effacement'],
            ]],
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'confirmed', 'label' => 'Confirmée'],
                ['value' => 'fulfilled', 'label' => 'Traitée'],
                ['value' => 'cancelled', 'label' => 'Annulée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'admin_response' => 'Réponse',
            'fulfilled_at' => 'Traitée le',
            'export_format' => 'Format',
        ];
    }

    public function globalActions(): array
    {
        return [
            // Appliquer les politiques de rétention à toute la plateforme.
            Action::make('run-retention', 'Appliquer la rétention', function (array $valeurs) {
                $stats = app(RetentionPolicyService::class)->enforceAll();

                return ['purged' => array_sum($stats)];
            }),
        ];
    }
}
