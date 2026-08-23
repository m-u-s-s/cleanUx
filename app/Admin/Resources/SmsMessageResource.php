<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\SmsMessage;
use App\Services\Notifications\SmsService;

/**
 * Le journal des SMS et messages WhatsApp.
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

    public function actions(): array
    {
        return [
            // Le refus est repris du web MOT POUR MOT : seuls les SMS en échec, non délivrés ou limités se retentent.
            Action::make('retry', 'Réessayer l’envoi', function (SmsMessage $message) {
                $retentable = [
                    SmsMessage::STATUS_FAILED,
                    SmsMessage::STATUS_UNDELIVERED,
                    SmsMessage::STATUS_RATE_LIMITED,
                ];

                if (! in_array($message->status, $retentable, true)) {
                    return ['ok' => false, 'message' => 'Seuls les SMS en échec peuvent être retentés.'];
                }

                // On renvoie le MÊME corps, pas un gabarit reconstruit : le message est déjà rédigé et traduit, et le régénérer risquerait d'envoyer autre chose que ce que le destinataire attendait.
                app(SmsService::class)->dispatch(
                    toPhone: (string) $message->to_phone,
                    body: (string) $message->body,
                    category: (string) $message->category,
                );

                return ['ok' => true];
            }),
        ];
    }
}
