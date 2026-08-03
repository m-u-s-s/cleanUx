<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\SmsMessage;

/**
 * Le journal des SMS et messages WhatsApp.
 *
 * Le cout est affiche : un canal facturé au message se surveille depuis la meme page que ses
 * échecs, sinon on découvre la dérive sur la facture.
 *
 * @extends EloquentResource<SmsMessage>
 */
class SmsMessageResource extends EloquentResource
{
    public function key(): string
    {
        return 'sms';
    }

    protected function model(): string
    {
        return SmsMessage::class;
    }

    protected function columnSpec(): array
    {
        return [
            'to_phone' => ['Numéro'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'category' => ['Catégorie', Column::TYPE_BADGE],
            'cost_eur' => ['Coût', Column::TYPE_MONEY],
            'created_at' => ['Émis le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['to_phone', 'body'];
    }

    protected function searchLabel(): string
    {
        return 'Numéro ou contenu';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'queued', 'label' => 'En file'],
                ['value' => 'sent', 'label' => 'Envoyé'],
                ['value' => 'delivered', 'label' => 'Délivré'],
                ['value' => 'failed', 'label' => 'Échoué'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'provider' => 'Fournisseur',
            'failed_reason' => 'Motif d’échec',
            'delivered_at' => 'Délivré le',
        ];
    }
}
