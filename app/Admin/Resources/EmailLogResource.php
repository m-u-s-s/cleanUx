<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\EmailLog;

/**
 * Le journal des emails partis.
 *
 * @extends EloquentResource<EmailLog>
 */
class EmailLogResource extends EloquentResource
{
    public function key(): string
    {
        return 'emails';
    }

    protected function model(): string
    {
        return EmailLog::class;
    }

    protected function columnSpec(): array
    {
        return [
            'to_email' => ['Destinataire'],
            'subject' => ['Objet'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'sent_at' => ['Envoyé le', Column::TYPE_DATETIME],
            'created_at' => ['Créé le', Column::TYPE_DATE],
        ];
    }

    protected function searchable(): array
    {
        return ['to_email', 'subject', 'template_key'];
    }

    protected function searchLabel(): string
    {
        return 'Destinataire, objet ou gabarit';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'sent', 'label' => 'Envoyé'],
                ['value' => 'failed', 'label' => 'Échoué'],
                ['value' => 'queued', 'label' => 'En file'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'error_message' => 'Message d’erreur',
            'template_key' => 'Gabarit',
            'failed_at' => 'Échoué le',
        ];
    }
}
