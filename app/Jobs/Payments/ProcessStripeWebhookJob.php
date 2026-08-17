<?php

namespace App\Jobs\Payments;

use App\Models\StripeWebhookEvent;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStripeWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * TROIS TENTATIVES, PARCE QU'UN ÉVÉNEMENT STRIPE PERDU NE REVIENT PAS.
     *
     * À une seule tentative, une coupure réseau d'une seconde vers Stripe, un verrou de base ou un
     * redémarrage du worker suffisaient à perdre définitivement l'événement — et avec lui
     * l'encaissement, le crédit du portefeuille et l'écriture comptable qui en dépendent. Rien ne
     * le rattrapait : Stripe considère l'événement remis dès que l'endpoint a répondu 200, ce que
     * le contrôleur fait AVANT de mettre le traitement en file.
     *
     * LE REJEU EST SANS DANGER ICI, et c'est ce qui autorise cette valeur : le traitement est
     * idempotent de bout en bout — `stripe_webhook_events` déduplique par identifiant d'événement,
     * les crédits de portefeuille par `idempotency_key`, les écritures comptables par leur propre
     * clé. Une seconde exécution ne produit rien de neuf.
     *
     * L'ATTENTE CROÎT : une seconde, puis dix, puis soixante. Un service momentanément indisponible
     * a besoin de temps, pas d'insistance — trois appels dans la même seconde échoueraient trois
     * fois pour la même raison.
     */
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 10, 60];

    public int $timeout = 60;

    public function __construct(public int $eventId) {}

    public function handle(StripeWebhookEventProcessor $processor): void
    {
        $event = StripeWebhookEvent::find($this->eventId);
        if (! $event) {
            return;
        }

        $processor->process($event);
    }

    /**
     * Backoff exponentiel pour retry de la queue (1m, 5m, 15m, 1h, 6h).
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 21600];
    }
}
