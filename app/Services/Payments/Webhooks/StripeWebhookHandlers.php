<?php

namespace App\Services\Payments\Webhooks;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Notifications\Payments\PaymentFailedNotification;
use App\Services\Payments\ProviderWalletService;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\StripeConnectService;
use App\Services\Tips\TipService;
use App\Support\Accounting\BookingAutoPoster;
use App\Support\Webhooks\BusinessEventEmitter;
use Illuminate\Support\Facades\Log;

/**
 * Implémentations des handlers Stripe par type d'événement.
 *
 * Extraits de StripeWebhookEventProcessor pour limiter la taille du god-object.
 * Behavior identique — aucune logique métier modifiée.
 */
class StripeWebhookHandlers
{
    public function __construct(
        protected StripeConnectService $connectService,
        protected StripeConnectPaymentService $paymentService,
        protected ProviderWalletService $walletService,
    ) {}

    public function handleAccountUpdated(array $account): array
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

    public function handlePayoutPaid(array $payout): array
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

    public function handlePayoutFailed(array $payout): array
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

    public function handleChargeRefunded(array $charge): array
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

        // Clawback strategy: iterate refunds.data so each distinct Stripe Refund
        // (re_xxx) gets its own idempotent clawback entry.  This unifies the
        // service path (refundMissionPayment passes re_xxx) and the webhook path:
        // because both keys on the Refund id, a service-then-webhook flow dedupes
        // to a single row.  Distinct partial refunds each have their own re_xxx so
        // they still produce separate clawbacks.
        //
        // Proportional formula per refund (same as StripeConnectPaymentService):
        //   clawbackCents = round(refundCents × providerCents / max(1, totalCents))
        $totalCents = max(1, (int) ($charge['amount'] ?? $booking->payment_amount_cents ?? 0));
        $providerCents = (int) ($booking->provider_amount_cents ?? $totalCents);

        $perRefundData = $charge['refunds']['data'] ?? [];

        if (! empty($perRefundData)) {
            // Preferred path: iterate individual refund objects keyed on re_xxx.
            foreach ($perRefundData as $refund) {
                $refundId = $refund['id'] ?? null;
                $refundCents = (int) ($refund['amount'] ?? 0);
                if ($refundCents <= 0) {
                    continue;
                }
                $clawbackCents = (int) round($refundCents * $providerCents / $totalCents);
                if ($clawbackCents <= 0) {
                    continue;
                }
                $this->walletService->recordRefundClawback(
                    $booking,
                    round($clawbackCents / 100, 2),
                    $refundId,
                );
            }
        } else {
            // Fallback: refunds.data absent (some legacy/test payloads).
            // Key on charge id — this path is only hit when no per-refund data is
            // available, so there is no overlap with the service path (which always
            // has a re_xxx).
            $clawbackCents = (int) round($refundedAmountCents * $providerCents / $totalCents);
            if ($clawbackCents > 0) {
                $this->walletService->recordRefundClawback(
                    $booking,
                    round($clawbackCents / 100, 2),
                    $charge['id'] ?? null,
                );
            }
        }

        BusinessEventEmitter::emit(
            eventCode: 'payment.refunded',
            payload: [
                'booking_id' => $booking->id,
                'amount_refunded_cents' => $refundedAmountCents,
                'currency' => $charge['currency'] ?? null,
                'stripe_charge_id' => $charge['id'] ?? null,
                'stripe_payment_intent_id' => $pi,
                'is_total' => $isTotal,
            ],
            idempotencyKey: 'payment.refunded:'.($charge['id'] ?? $pi),
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );
        BookingAutoPoster::postRefund($booking, $refundedAmountCents);

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
            'booking_id' => $booking->id,
            'is_total' => $isTotal,
        ]];
    }

    public function handlePaymentIntentSucceeded(array $intent): array
    {
        $piId = $intent['id'] ?? null;
        if (! $piId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        // 1) Si c'est un payment intent de TIP, confirmCharge le tip
        $this->maybeConfirmTipCharge($intent, $piId);

        $booking = Booking::query()->where('stripe_payment_intent_id', $piId)->first();
        if (! $booking) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $previousStatus = $booking->payment_status;
        $this->paymentService->syncPaymentIntent($booking);
        $booking->refresh();

        if ($booking->payment_status === 'captured') {
            // recordEarning is idempotent (idempotency_key prevents double-write),
            // so it is safe to call regardless of previousStatus.
            // Bug fix: the original guard ($previousStatus !== 'captured') caused
            // recordEarning to be skipped when captureMissionPayment had already
            // set payment_status='captured' before this webhook arrived, resulting
            // in the wallet never being credited in the standard capture→webhook flow.
            $this->walletService->recordEarning($booking, $intent);

            $feeCents = (int) (data_get($intent, 'charges.data.0.balance_transaction.fee')
                ?? data_get($intent, 'application_fee_amount')
                ?? 0);

            if ($previousStatus !== 'captured') {
                // Only emit the business event and accounting post on the first
                // transition to 'captured' to avoid duplicate downstream effects.
                BusinessEventEmitter::emit(
                    eventCode: 'payment.succeeded',
                    payload: [
                        'booking_id' => $booking->id,
                        'amount_cents' => (int) ($intent['amount'] ?? 0),
                        'currency' => $intent['currency'] ?? null,
                        'stripe_payment_intent_id' => $piId,
                        'fees_cents' => $feeCents,
                    ],
                    idempotencyKey: 'payment.succeeded:'.$piId,
                    sourceType: Booking::class,
                    sourceId: (int) $booking->id,
                );
                BookingAutoPoster::postPayment($booking, $feeCents);
            }
        }

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
            'booking_id' => $booking->id,
            'transitioned_to_captured' => $booking->payment_status === 'captured' && $previousStatus !== 'captured',
        ]];
    }

    /**
     * Si un PaymentIntent succeeded correspond à un BookingTip, le confirmCharge.
     * Filtre via metadata.tip_id OU lookup stripe_payment_intent_id sur booking_tips.
     */
    public function maybeConfirmTipCharge(array $intent, string $piId): void
    {
        try {
            if (! class_exists(BookingTip::class)) {
                return;
            }
            $tip = BookingTip::query()
                ->where('stripe_payment_intent_id', $piId)
                ->where('status', BookingTip::STATUS_PENDING)
                ->first();
            if (! $tip) {
                $tipId = data_get($intent, 'metadata.tip_id');
                if ($tipId) {
                    $tip = BookingTip::query()
                        ->where('id', $tipId)
                        ->where('status', BookingTip::STATUS_PENDING)
                        ->first();
                }
            }
            if ($tip) {
                app(TipService::class)->confirmCharge($tip, $piId);
            }
        } catch (\Throwable $e) {
            Log::warning('[tips_webhook] confirmCharge failed', [
                'pi_id' => $piId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handlePaymentIntentFailed(array $intent): array
    {
        $piId = $intent['id'] ?? null;
        if (! $piId) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $booking = Booking::query()->where('stripe_payment_intent_id', $piId)->first();
        if (! $booking) {
            return ['status' => StripeWebhookEvent::STATUS_IGNORED];
        }

        $alreadyFailed = $booking->payment_status === 'failed';

        if (! $alreadyFailed) {
            $booking->update([
                'payment_status' => 'failed',
                'payment_failed_at' => now(),
            ]);

            // Notify the client (soft-fail: don't let notification errors abort the webhook)
            try {
                $client = $booking->client ?? User::find($booking->customer_user_id ?? $booking->client_id);
                if ($client) {
                    $failureMessage = data_get($intent, 'last_payment_error.message');
                    $client->notify(new PaymentFailedNotification($booking, $failureMessage));
                }
            } catch (\Throwable $e) {
                Log::warning('[payment_failed_webhook] notification failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        BusinessEventEmitter::emit(
            eventCode: 'payment.failed',
            payload: [
                'booking_id' => $booking->id,
                'amount_cents' => (int) ($intent['amount'] ?? 0),
                'currency' => $intent['currency'] ?? null,
                'stripe_payment_intent_id' => $piId,
                'failure_message' => data_get($intent, 'last_payment_error.message'),
                'failure_code' => data_get($intent, 'last_payment_error.code'),
            ],
            idempotencyKey: 'payment.failed:'.$piId,
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );

        return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => ['booking_id' => $booking->id]];
    }

    public function handleTransferCreated(array $transfer): array
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

        // Sync stripe_transfer_id + payout_status on booking if referenced in metadata
        $bookingId = $transfer['metadata']['booking_id'] ?? null;
        if ($bookingId) {
            $booking = Booking::query()->find($bookingId);
            if ($booking && empty($booking->stripe_transfer_id)) {
                $booking->update([
                    'stripe_transfer_id' => $stripeTransferId,
                    'payout_status' => 'transferred',
                ]);

                return ['status' => StripeWebhookEvent::STATUS_PROCESSED, 'details' => [
                    'booking_id' => $booking->id,
                    'stripe_transfer_id' => $stripeTransferId,
                ]];
            }
        }

        return ['status' => StripeWebhookEvent::STATUS_IGNORED, 'details' => ['reason' => 'transfer_noted_no_action']];
    }
}
