<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\BroadcastEvent;

/**
 * Le journal des diffusions temps réel.
 *
 * Le REJEU d’une diffusion passe par le module temps réel, qui garde l’idempotence : rejouer
 * depuis ici sans sa cle enverrait un doublon a tous les abonnes du canal.
 *
 * @extends EloquentResource<BroadcastEvent>
 */
class BroadcastEventResource extends EloquentResource
{
    public function key(): string
    {
        return 'realtime';
    }

    protected function model(): string
    {
        return BroadcastEvent::class;
    }

    protected function columnSpec(): array
    {
        return [
            'channel' => ['Canal'],
            'broadcast_as' => ['Événement'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'attempts' => ['Tentatives', Column::TYPE_NUMBER],
            'created_at' => ['Émis le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['channel', 'broadcast_as'];
    }

    protected function searchLabel(): string
    {
        return 'Canal ou événement';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'queued', 'label' => 'En file'],
                ['value' => 'sent', 'label' => 'Envoyé'],
                ['value' => 'failed', 'label' => 'Échoué'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'failed_reason' => 'Motif d’échec',
            'audience' => 'Audience',
            'sent_at' => 'Envoyé le',
        ];
    }
}
