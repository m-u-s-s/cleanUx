<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\WebhookEndpoint;

/**
 * Les points de sortie webhook des intégrations B2B. NI ROTATION DE SECRET NI REJEU ICI.
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

    public function actions(): array
    {
        return [
            // Faire tourner le secret invalide immédiatement les signatures en cours de route : c'est le geste d'urgence quand un secret a fuité, et il doit être atteignable depuis un téléphone — c'est précisément là qu'on est quand on l'apprend.
            Action::make('rotate-secret', 'Faire tourner le secret', function (WebhookEndpoint $endpoint) {
                $endpoint->forceFill(['secret' => WebhookEndpoint::generateSecret()])->save();

                return ['ok' => true];
            })->destructive('Les intégrations devront reprendre le nouveau secret.'),

            Action::make('toggle-suspend', 'Suspendre / réactiver', function (WebhookEndpoint $endpoint) {
                // Réactiver REMET le compteur d'échecs à zéro : sans cela l'auto-suspension
                // reprendrait au premier échec suivant, et l'endpoint retomberait aussitôt.
                $endpoint->forceFill([
                    'is_suspended' => ! $endpoint->is_suspended,
                    'suspension_reason' => $endpoint->is_suspended ? null : 'Suspendu depuis le mobile',
                    'consecutive_failures' => $endpoint->is_suspended ? 0 : $endpoint->consecutive_failures,
                ])->save();

                return ['is_suspended' => (bool) $endpoint->fresh()->is_suspended];
            }),
        ];
    }
}
