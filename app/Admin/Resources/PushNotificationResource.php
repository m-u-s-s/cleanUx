<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\PushNotification;

/**
 * Le journal des notifications poussées.
 *
 * @extends EloquentResource<PushNotification>
 */
class PushNotificationResource extends EloquentResource
{
    public function key(): string
    {
        return 'push';
    }

    protected function model(): string
    {
        return PushNotification::class;
    }

    protected function columnSpec(): array
    {
        return [
            'title' => ['Titre'],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'status' => ['Statut', Column::TYPE_BADGE],
            'attempts' => ['Tentatives', Column::TYPE_NUMBER],
            'created_at' => ['Émise le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['title', 'body'];
    }

    protected function searchLabel(): string
    {
        return 'Titre ou contenu';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'queued', 'label' => 'En file'],
                ['value' => 'sent', 'label' => 'Envoyée'],
                ['value' => 'failed', 'label' => 'Échouée'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'provider' => 'Fournisseur',
            'failed_reason' => 'Motif d’échec',
            'sent_at' => 'Envoyée le',
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('retry', 'Réessayer l’envoi', function (PushNotification $notif) {
                // Même refus que sur le web : renvoyer une notification déjà partie la ferait
                // sonner deux fois, et rien n'indiquerait au destinataire laquelle compte.
                $retentable = [PushNotification::STATUS_FAILED, PushNotification::STATUS_RATE_LIMITED];

                if (! in_array($notif->status, $retentable, true)) {
                    return ['ok' => false, 'message' => 'Seules les notifications en échec peuvent être retentées.'];
                }

                $notif->forceFill(['status' => PushNotification::STATUS_QUEUED, 'failed_reason' => null])->save();

                return ['ok' => true];
            }),
        ];
    }
}
