<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\PushNotification;

/**
 * Le journal des notifications poussées.
 *
 * Aucun renvoi : une notification échouée l’est souvent parce que le jeton d’appareil est mort.
 * La renvoyer en boucle n’atteint personne et use le quota du fournisseur.
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
}
