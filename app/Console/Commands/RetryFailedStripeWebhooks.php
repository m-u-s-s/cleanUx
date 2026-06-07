<?php

namespace App\Console\Commands;

use App\Jobs\Payments\ProcessStripeWebhookJob;
use App\Models\StripeWebhookEvent;
use Illuminate\Console\Command;

/**
 * M9 — re-dispatch Stripe Connect webhook events that failed transiently.
 *
 * ProcessStripeWebhookJob has tries=1, and the controller always answers Stripe 200, so a
 * transiently-failed event (DB blip, Stripe API timeout) is never retried by the queue and
 * Stripe never re-sends it. The processor marks such events STATUS_FAILED with next_retry_at;
 * this command (scheduled) re-dispatches the ones now due, until they succeed or hit
 * max_attempts (then the processor moves them to dead_letter). Without it, a missed
 * payout.failed / charge.refunded silently desyncs the wallet + booking from Stripe.
 */
class RetryFailedStripeWebhooks extends Command
{
    protected $signature = 'stripe:retry-failed-webhooks {--limit=200 : Max events to re-dispatch per run}';

    protected $description = 'Re-dispatch failed Stripe webhook events that are due for retry';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $due = StripeWebhookEvent::query()
            ->dueForRetry()
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No Stripe webhook events due for retry.');

            return self::SUCCESS;
        }

        foreach ($due as $event) {
            ProcessStripeWebhookJob::dispatch($event->id);
        }

        $this->info("Re-dispatched {$due->count()} failed Stripe webhook event(s).");

        return self::SUCCESS;
    }
}
