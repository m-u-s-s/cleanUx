<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\UserReport;

/**
 * Les signalements entre utilisateurs.
 *
 * La DÉCISION passe par le module Sécurité, qui peut bloquer un compte et notifier les deux
 * parties. Poser un statut ici classerait le signalement sans rien empêcher — la personne
 * signalée continuerait d’intervenir.
 *
 * @extends EloquentResource<UserReport>
 */
class UserReportResource extends EloquentResource
{
    public function key(): string
    {
        return 'safety';
    }

    protected function model(): string
    {
        return UserReport::class;
    }

    protected function columnSpec(): array
    {
        return [
            'code' => ['Référence'],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'description' => ['Description'],
            'created_at' => ['Signalé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['code', 'description'];
    }

    protected function searchLabel(): string
    {
        return 'Référence ou description';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'reviewed', 'label' => 'Traité'],
                ['value' => 'dismissed', 'label' => 'Écarté'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'admin_notes' => 'Notes internes',
            'reviewed_at' => 'Traité le',
        ];
    }
}
