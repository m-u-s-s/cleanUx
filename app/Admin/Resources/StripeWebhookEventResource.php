<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\StripeWebhookEvent;
use App\Support\ActivityLogger;

/**
 * Les événements Stripe reçus et leur traitement.
 *
 * Le REJEU passe par le module Stripe, qui tient l’idempotence : rejouer un événement de
 * paiement sans sa clé pourrait créditer deux fois. C’est exactement le genre de défaut que la
 * plateforme a déjà corrigé une fois.
 *
 * @extends EloquentResource<StripeWebhookEvent>
 */
class StripeWebhookEventResource extends EloquentResource
{
    public function key(): string
    {
        return 'stripe';
    }

    protected function model(): string
    {
        return StripeWebhookEvent::class;
    }

    protected function columnSpec(): array
    {
        return [
            'type' => ['Événement'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'attempts' => ['Tentatives', Column::TYPE_NUMBER],
            'received_at' => ['Reçu le', Column::TYPE_DATETIME],
            'processed_at' => ['Traité le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['stripe_event_id', 'type'];
    }

    protected function searchLabel(): string
    {
        return 'Identifiant ou type';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'pending', 'label' => 'En attente'],
                ['value' => 'processed', 'label' => 'Traité'],
                ['value' => 'failed', 'label' => 'Échoué'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'last_error' => 'Dernière erreur',
            'next_retry_at' => 'Prochaine tentative',
            'stripe_event_id' => 'Identifiant Stripe',
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * Le REFUS est repris du web : un évènement qui n'est ni retentable ni en lettre morte
             * a déjà été traité. Le relancer rejouerait un paiement ou un remboursement — la seule
                 * catégorie d'erreur de ce chantier qui coûte de l'argent réel.
             */
            Action::make('retry', 'Relancer le traitement', function (StripeWebhookEvent $event) {
                $relancable = $event->canRetry()
                    || $event->status === StripeWebhookEvent::STATUS_DEAD_LETTER;

                if (! $relancable) {
                    return ['ok' => false, 'message' => 'Cet évènement a déjà été traité.'];
                }

                $event->forceFill([
                    'status' => StripeWebhookEvent::STATUS_RECEIVED,
                    'next_retry_at' => null,
                ])->save();

                return ['ok' => true];
            }),

            Action::make('mark-ignored', 'Marquer ignoré', function (StripeWebhookEvent $event) {
                $event->forceFill([
                    'status' => StripeWebhookEvent::STATUS_IGNORED,
                    'processed_at' => now(),
                ])->save();

                ActivityLogger::log('stripe.webhook_event_manual_ignored', $event, [
                    'admin_user_id' => request()->user()?->id,
                ]);

                return ['ok' => true];
            })->destructive('L’évènement ne sera plus traité.'),
        ];
    }
}
