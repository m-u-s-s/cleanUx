<?php

namespace App\Console\Commands;

use App\Jobs\WebhooksV2\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * LE MOTEUR QUI MANQUAIT DERRIÈRE `next_retry_at`.
 *
 * `WebhookDeliveryRunner:129` écrit fidèlement la date de reprise, `WebhookDelivery::scopeDue()`
 * sait la lire — et ce scope N'AVAIT AUCUN APPELANT. `DeliverWebhookJob` porte `$tries = 1` avec
 * en commentaire « retries gérés en interne via next_retry_at » : la politique était donc écrite
 * des deux côtés, et exécutée par personne.
 *
 * Conséquence mesurée : un point d'arrivée client qui répondait 502 dix secondes passait `failed`
 * à la première tentative et n'était plus jamais retenté. `max_attempts` n'était jamais atteint,
 * donc pas de dead-letter, donc pas d'auto-suspension non plus. Le tableau de bord affichait
 * « pending » indéfiniment.
 */
class RejouerLesLivraisonsDeWebhook extends Command
{
    protected $signature = 'webhooks:rejouer-les-livraisons {--limit=200 : Nombre maximum de livraisons par passage}';

    protected $description = 'Renvoie les livraisons de webhook sortant dont la date de reprise est échue';

    public function handle(): int
    {
        $echues = WebhookDelivery::query()
            ->due()
            ->notTerminal()
            ->orderByRaw('next_retry_at is null desc')
            ->orderBy('next_retry_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        if ($echues->isEmpty()) {
            $this->info('Aucune livraison de webhook à reprendre.');

            return self::SUCCESS;
        }

        foreach ($echues as $livraison) {
            DeliverWebhookJob::dispatch($livraison->id);
        }

        $this->info($echues->count().' livraison(s) de webhook remise(s) en file.');

        return self::SUCCESS;
    }
}
