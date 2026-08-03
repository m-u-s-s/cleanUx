<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\ChatThread;

/**
 * Les fils de discussion et leur modération.
 *
 * La MODÉRATION s’exerce sur un MESSAGE, pas sur un fil : c’est le message qui porte le contenu
 * signalé. Cette liste sert à retrouver le fil ; la décision se prend sur la page dédiée, qui
 * montre le contenu en cause.
 *
 * @extends EloquentResource<ChatThread>
 */
class ChatThreadResource extends EloquentResource
{
    public function key(): string
    {
        return 'chat';
    }

    protected function model(): string
    {
        return ChatThread::class;
    }

    protected function columnSpec(): array
    {
        return [
            'title' => ['Fil'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'message_count' => ['Messages', Column::TYPE_NUMBER],
            'flagged_count' => ['Signalés', Column::TYPE_NUMBER],
            'last_message_at' => ['Dernier message', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['title', 'code', 'last_message_preview'];
    }

    protected function searchLabel(): string
    {
        return 'Titre ou dernier message';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'open', 'label' => 'Ouvert'],
                ['value' => 'closed', 'label' => 'Clos'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'context_type' => 'Contexte',
            'is_archived' => 'Archivé',
        ];
    }
}
