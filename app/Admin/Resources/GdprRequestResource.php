<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\GdprDataRequest;

/**
 * Les demandes RGPD — export et droit à l’oubli.
 *
 * AUCUNE EXÉCUTION DEPUIS LA CONSOLE. Un effacement RGPD est irréversible et traverse une
 * vingtaine de tables ; il passe par le module dédié, qui respecte le délai de grâce pendant
 * lequel la personne peut encore se raviser.
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
}
