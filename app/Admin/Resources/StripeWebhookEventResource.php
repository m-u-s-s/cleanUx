<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\StripeWebhookEvent;

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
}
