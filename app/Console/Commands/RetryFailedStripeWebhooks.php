<?php

namespace App\Console\Commands;

use App\Jobs\Payments\ProcessStripeWebhookJob;
use App\Models\StripeWebhookEvent;
use Illuminate\Console\Command;

/** M9 — re-dispatch Stripe Connect webhook events that failed transiently. */
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
