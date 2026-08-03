<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\WebhookEndpoint;

/**
 * Les points de sortie webhook des intégrations B2B.
 *
 * NI ROTATION DE SECRET NI REJEU ICI. Faire tourner un secret coupe l’intégration du client
 * jusqu’à ce qu’il l’ait repris ; ce geste s’accompagne d’un avertissement que la page dédiée
 * affiche et qu’un bouton de liste ne montrerait pas.
 *
 * @extends EloquentResource<WebhookEndpoint>
 */
class WebhookEndpointResource extends EloquentResource
{
    public function key(): string
    {
        return 'webhooks';
    }

    protected function model(): string
    {
        return WebhookEndpoint::class;
    }

    protected function columnSpec(): array
    {
        return [
            'name' => ['Point de sortie'],
            'url' => ['URL'],
            'is_active' => ['Actif', Column::TYPE_BOOL],
            'consecutive_failures' => ['Échecs consécutifs', Column::TYPE_NUMBER],
            'is_suspended' => ['Suspendu', Column::TYPE_BOOL],
        ];
    }

    protected function searchable(): array
    {
        return ['name', 'url', 'code'];
    }

    protected function searchLabel(): string
    {
        return 'Nom, URL ou code';
    }

    protected function detailSpec(): array
    {
        return [
            'suspension_reason' => 'Motif de suspension',
            'last_success_at' => 'Dernier succès',
            'last_failure_at' => 'Dernier échec',
            'max_attempts' => 'Tentatives max',
        ];
    }
}
