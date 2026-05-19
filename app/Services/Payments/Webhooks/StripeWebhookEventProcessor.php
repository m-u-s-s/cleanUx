<?php

namespace App\Services\Payments\Webhooks;

use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\Payments\ProviderWalletService;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\StripeConnectService;
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
        protected StripeConnectService $connectService,
        protected StripeConnectPaymentService $paymentService,
        protected ProviderWalletService $walletService,
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
            $type === 'account.updated' => $this->handleAccountUpdated($data),
            $type === 'payout.paid' => $this->handlePayoutPaid($data),
            $type === 'payout.failed' => $this->handlePayoutFailed($data),
            $type === 'charge.refunded' => $this->handleChargeRefunded($data),
            $type === 'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($data),
            $type === 'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($data),
            $type === 'transfer.created' => $this->handleTransferCreated($data),
            default => ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'unhandled_type', 'type' => $type]],
        };
    }

    protected function handleAccountUpdated(array $account): array
    {
        $accountId = $account['id'] ?? null;
        if (! $accountId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $user = User::query()->where('stripe_connect_account_id', $accountId)->first();
        if (! $user) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'no_user_for_account']];
        }

        if (method_exists($this->connectService, 'syncAccountStatus')) {
            $this->connectService->syncAccountStatus($user);
        }

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['user_id' => $user->id]];
    }

    protected function handlePayoutPaid(array $payout): array
    {
        $stripePayoutId = $payout['id'] ?? null;
        if (! $stripePayoutId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $payoutModel = ProviderPayout::query()
            ->where('provider_payout_id', $stripePayoutId)
            ->first();

        if (! $payoutModel) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'no_local_payout']];
        }

        if ($payoutModel->status === ProviderPayout::STATUS_PAID) {
            return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['already' => true]];
        }

        $payoutModel->markAsPaid($stripePayoutId);
        $this->walletService->markPayoutCleared($payoutModel, $stripePayoutId);

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['payout_id' => $payoutModel->id]];
    }

    protected function handlePayoutFailed(array $payout): array
    {
        $stripePayoutId = $payout['id'] ?? null;
        if (! $stripePayoutId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $payoutModel = ProviderPayout::query()
            ->where('provider_payout_id', $stripePayoutId)
            ->first();

        if (! $payoutModel) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $payoutModel->markAsFailed([
            'failure_code' => $payout['failure_code'] ?? null,
            'failure_message' => $payout['failure_message'] ?? null,
        ]);

        $this->walletService->reversePayout($payoutModel, $payout['failure_message'] ?? 'stripe_payout_failed');

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['payout_id' => $payoutModel->id]];
    }

    protected function handleChargeRefunded(array $charge): array
    {
        $pi = $charge['payment_intent'] ?? null;
        if (! $pi) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $booking = Booking::query()->where('stripe_payment_intent_id', $pi)->first();
        if (! $booking) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $isTotal = (int) ($charge['amount_refunded'] ?? 0) >= (int) ($charge['amount'] ?? 0);
        $refundedAmountCents = (int) ($charge['amount_refunded'] ?? 0);

        $alreadyHandled = $booking->payment_status === ($isTotal ? 'refunded' : 'partially_refunded');

        if (! $alreadyHandled) {
            $booking->update([
                'payment_status' => $isTotal ? 'refunded' : 'partially_refunded',
                'payment_refunded_at' => now(),
            ]);
        }

        $this->walletService->recordRefundClawback(
            $booking,
            round($refundedAmountCents / 100, 2),
            $charge['id'] ?? null,
        );

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
            'booking_id' => $booking->id,
            'is_total' => $isTotal,
        ]];
    }

    protected function handlePaymentIntentSucceeded(array $intent): array
    {
        $piId = $intent['id'] ?? null;
        if (! $piId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $booking = Booking::query()->where('stripe_payment_intent_id', $piId)->first();
        if (! $booking) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $previousStatus = $booking->payment_status;
        $this->paymentService->syncPaymentIntent($booking);
        $booking->refresh();

        if ($booking->payment_status === 'captured' && $previousStatus !== 'captured') {
            $this->walletService->recordEarning($booking, $intent);
        }

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
            'booking_id' => $booking->id,
            'transitioned_to_captured' => $booking->payment_status === 'captured' && $previousStatus !== 'captured',
        ]];
    }

    protected function handlePaymentIntentFailed(array $intent): array
    {
        $piId = $intent['id'] ?? null;
        if (! $piId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $booking = Booking::query()->where('stripe_payment_intent_id', $piId)->first();
        if (! $booking) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        if ($booking->payment_status !== 'failed') {
            $booking->update([
                'payment_status' => 'failed',
                'payment_failed_at' => now(),
            ]);
        }

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['booking_id' => $booking->id]];
    }

    protected function handleTransferCreated(array $transfer): array
    {
        $stripeTransferId = $transfer['id'] ?? null;
        if (! $stripeTransferId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $existing = ProviderWalletTransaction::query()
            ->where('stripe_transfer_id', $stripeTransferId)
            ->exists();

        if ($existing) {
            return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['already' => true]];
        }

        return ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'transfer_noted_no_action']];
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
