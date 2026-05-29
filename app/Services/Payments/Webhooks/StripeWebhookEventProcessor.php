<?php

namespace App\Services\Payments\Webhooks;

use App\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traitement idempotent des webhooks Stripe Connect.
 *
 * - Chaque appel à process() est ré-entrant (peut tourner N fois sans effet de bord)
 * - Marquage transactionnel : processing → processed/failed atomique
 * - Backoff exponentiel sur retry
 * - Dead letter après max_attempts
 */
class StripeWebhookEventProcessor
{
    public function __construct(
        protected StripeWebhookHandlers $handlers,
    ) {}

    public function process(StripeWebhookEvent $event): void
    {
        if ($event->isTerminal()) {
            return;
        }

        $locked = DB::transaction(function () use ($event) {
            $fresh = StripeWebhookEvent::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->first();

            if (! $fresh || $fresh->isTerminal()) {
                return null;
            }

            $fresh->update([
                'status' => StripeWebhookEvent::STATUS_PROCESSING,
                'attempts' => $fresh->attempts + 1,
                'first_attempted_at' => $fresh->first_attempted_at ?? now(),
            ]);

            return $fresh;
        });

        if (! $locked) {
            return;
        }

        try {
            $result = $this->dispatchByType($locked);

            $locked->update([
                'status' => $result['status'] ?? StripeWebhookEvent::STATUS_PROCESSED,
                'result' => $result['details'] ?? null,
                'processed_at' => now(),
                'last_error' => null,
                'next_retry_at' => null,
            ]);
        } catch (\Throwable $e) {
            $this->recordFailure($locked, $e);
            throw $e;
        }
    }

    /**
     * @return array{status:string, details?:array}
     */
    protected function dispatchByType(StripeWebhookEvent $event): array
    {
        $type = $event->type;
        $data = $event->payload['data']['object'] ?? null;

        if (! $data) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'no_payload_data']];
        }

        return match (true) {
            $type === 'account.updated' => $this->handlers->handleAccountUpdated($data),
            $type === 'payout.paid' => $this->handlers->handlePayoutPaid($data),
            $type === 'payout.failed' => $this->handlers->handlePayoutFailed($data),
            $type === 'charge.refunded' => $this->handlers->handleChargeRefunded($data),
            $type === 'payment_intent.succeeded' => $this->handlers->handlePaymentIntentSucceeded($data),
            $type === 'payment_intent.payment_failed' => $this->handlers->handlePaymentIntentFailed($data),
            $type === 'transfer.created' => $this->handlers->handleTransferCreated($data),
            default => ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'unhandled_type', 'type' => $type]],
        };
    }

    protected function recordFailure(StripeWebhookEvent $event, \Throwable $e): void
    {
        $attempts = $event->attempts;
        $maxAttempts = $event->max_attempts;
        $isDeadLetter = $attempts >= $maxAttempts;

        $delaySeconds = min(3600, (int) (2 ** $attempts) * 30);

        $event->update([
            'status' => $isDeadLetter ? StripeWebhookEvent::STATUS_DEAD_LETTER : StripeWebhookEvent::STATUS_FAILED,
            'last_error' => $e->getMessage(),
            'next_retry_at' => $isDeadLetter ? null : now()->addSeconds($delaySeconds),
        ]);

        Log::error('StripeWebhookEventProcessor: traitement échoué', [
            'event_id' => $event->id,
            'stripe_event_id' => $event->stripe_event_id,
            'type' => $event->type,
            'attempts' => $attempts,
            'dead_letter' => $isDeadLetter,
            'error' => $e->getMessage(),
        ]);
    }
}
