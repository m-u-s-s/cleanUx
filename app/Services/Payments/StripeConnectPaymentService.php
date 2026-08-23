<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Services\Finance\FinanceCreditNoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

/** Phase 13 — Service complet de paiements Stripe Connect. */
class StripeConnectPaymentService
{
    public function __construct(private ?ProviderWalletService $walletService = null)
    {
        if ($key = config('cashier.secret')) {
            Stripe::setApiKey($key);
        }
        $this->walletService ??= new ProviderWalletService;
    }

    /** Capture le PaymentIntent d'une mission terminée et crée une entrée ProviderPayout. */
    public function captureMissionPayment(Mission $mission): ?ProviderPayout
    {
        $booking = $mission->booking;
        if (! $booking || ! $booking->stripe_payment_intent_id) {
            Log::info('StripeConnectPaymentService: aucun PI à capturer', [
                'mission_id' => $mission->id,
            ]);

            return null;
        }

        if ($booking->payment_status !== 'authorized') {
            Log::info('StripeConnectPaymentService: PI pas en authorized', [
                'mission_id' => $mission->id,
                'payment_status' => $booking->payment_status,
            ]);

            return null;
        }

        // Attempt the Stripe capture OUTSIDE the DB transaction so that a
        // declined-card exception does not roll back the 'failed' status update.
        // The 'failed' write must survive even when Stripe rejects the capture.
        try {
            $intent = PaymentIntent::retrieve($booking->stripe_payment_intent_id);
            $intent->capture();
        } catch (\Throwable $e) {
            Log::error('StripeConnectPaymentService: capture failed', [
                'mission_id' => $mission->id,
                'pi_id' => $booking->stripe_payment_intent_id,
                'error' => $e->getMessage(),
            ]);

            // Write 'failed' outside any transaction so it is never rolled back.
            $booking->forceFill([
                'payment_status' => 'failed',
                'payment_failed_at' => now(),
            ])->save();
            throw new RuntimeException('Capture échouée : '.$e->getMessage(), 0, $e);
        }

        // Capture succeeded — wrap only the DB writes (status + payout row) in a
        // transaction so they are atomic with respect to each other.
        return DB::transaction(function () use ($mission, $booking) {
            $booking->forceFill([
                'payment_status' => 'captured',
                'payment_captured_at' => now(),
            ])->save();

            // Créer l'entrée ProviderPayout (entrée comptable côté Brio)
            $payout = $this->createProviderPayout($mission, $booking);

            Log::info('StripeConnectPaymentService: capture + payout entry OK', [
                'mission_id' => $mission->id,
                'payout_id' => $payout->id,
                'amount' => $payout->amount,
            ]);

            return $payout;
        });
    }

    /** Crée une entrée ProviderPayout pour une mission capturée. */
    public function createProviderPayout(Mission $mission, Booking $booking): ProviderPayout
    {
        // Provider user_id : priorité au lead_provider, sinon premier assignment accepté
        $providerUserId = $mission->lead_provider_user_id;
        if (! $providerUserId) {
            $accepted = $mission->assignments()
                ->where('assignment_status', 'accepted')
                ->first();
            $providerUserId = $accepted?->user_id;
        }

        if (! $providerUserId) {
            throw new RuntimeException('Mission sans prestataire identifiable pour créer le payout.');
        }

        // Montant en euros (decimal:2)
        $amount = $booking->provider_amount_cents !== null
            ? round((float) $booking->provider_amount_cents / 100, 2)
            : round(
                (float) ($mission->client_price ?? 0) - (float) ($mission->platform_commission ?? 0),
                2
            );

        $currency = strtoupper((string) ($booking->currency ?? 'EUR'));

        return ProviderPayout::create([
            'provider_user_id' => $providerUserId,
            'provider_organization_id' => optional($mission->lead_provider_user)->current_organization_id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => ProviderPayout::STATUS_PENDING,
            'provider' => 'stripe_connect',
            'period_start' => now()->startOfDay()->toDateString(),
            'period_end' => now()->endOfDay()->toDateString(),
            'metadata' => [
                'mission_id' => $mission->id,
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'stripe_payment_intent_id' => $booking->stripe_payment_intent_id,
                'platform_fee_cents' => $booking->platform_fee_cents,
                'provider_amount_cents' => $booking->provider_amount_cents,
            ],
        ]);
    }

    /**
     * Refund total ou partiel d'un paiement déjà capturé.
     *
     * @param  int|null  $amountCents  Montant à refund. null = total.
     * @param  string|null  $reason  'requested_by_customer' | 'duplicate' | 'fraudulent'
     */
    public function refundMissionPayment(
        Booking $booking,
        ?int $amountCents = null,
        ?string $reason = null,
    ): ?Refund {
        if (! $booking->stripe_payment_intent_id) {
            return null;
        }

        if ($booking->payment_status !== 'captured') {
            throw new RuntimeException(
                "Cannot refund booking with payment_status={$booking->payment_status}"
            );
        }

        $refundParams = [
            'payment_intent' => $booking->stripe_payment_intent_id,
        ];

        if ($amountCents !== null) {
            $refundParams['amount'] = $amountCents;
        }
        if ($reason && in_array($reason, ['requested_by_customer', 'duplicate', 'fraudulent'], true)) {
            $refundParams['reason'] = $reason;
        }

        try {
            $refund = Refund::create($refundParams);
        } catch (\Throwable $e) {
            Log::error('StripeConnectPaymentService: refund failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Refund échoué : '.$e->getMessage(), 0, $e);
        }

        $isTotal = $amountCents === null || $amountCents >= ($booking->payment_amount_cents ?? 0);

        $booking->forceFill([
            'payment_status' => $isTotal ? 'refunded' : 'partially_refunded',
            'payment_refunded_at' => now(),
        ])->save();

        // F3 — clawback: debit the provider wallet so they do not keep money
        // that was returned to the client.
        //
        // Proportional formula (unifies full + partial):
        //   clawbackCents = round(refundedCents × providerCents / max(1, totalCents))
        //
        // Full refund (amountCents=null): refundedCents = payment_amount_cents
        //   → clawbackCents = payment_amount_cents × provider_amount_cents / payment_amount_cents
        //   → = provider_amount_cents  ✓ (provider loses their full share)
        //
        // Partial refund of €50 on €100 booking (€80 provider):
        //   → 5000 × 8000 / 10000 = 4000 cents = €40  ✓ (NOT the raw €50)
        //
        // Idempotency key = Stripe Refund id (re_xxx), same key used by
        // handleChargeRefunded, so service-then-webhook deduplicates to one row.
        $totalCents = max(1, (int) ($booking->payment_amount_cents ?? 0));
        $providerCents = (int) ($booking->provider_amount_cents ?? $booking->payment_amount_cents ?? 0);

        if ($booking->provider_amount_cents === null) {
            Log::warning('refundMissionPayment: booking has null provider_amount_cents; clawback may over-claw', [
                'booking_id' => $booking->id,
            ]);
        }

        $refundedCents = $amountCents ?? $totalCents;
        $clawbackCents = min((int) round($refundedCents * $providerCents / $totalCents), $providerCents);

        if ($clawbackCents > 0) {
            $clawbackAmount = round((float) $clawbackCents / 100, 2);
            $this->walletService->recordRefundClawback(
                $booking,
                $clawbackAmount,
                $refund->id,
            );
        }

        // Audit HIGH — avoir (credit note) pour conformité comptable BE/FR.
        // Idempotent par refund id ; soft-fail pour ne jamais bloquer le flux d'argent.
        try {
            app(FinanceCreditNoteService::class)
                ->createForRefund($booking, (int) $refundedCents, $refund->id, $reason);
        } catch (\Throwable $e) {
            Log::warning('StripeConnectPaymentService: credit note generation failed', [
                'booking_id' => $booking->id,
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('StripeConnectPaymentService: refund OK', [
            'booking_id' => $booking->id,
            'refund_id' => $refund->id,
            'amount' => $refund->amount,
            'is_total' => $isTotal,
        ]);

        return $refund;
    }

    /** Re-synchronise l'état du PaymentIntent depuis Stripe vers le booking. */
    public function syncPaymentIntent(Booking $booking): void
    {
        if (! $booking->stripe_payment_intent_id) {
            return;
        }

        try {
            $intent = PaymentIntent::retrieve($booking->stripe_payment_intent_id);
        } catch (\Throwable $e) {
            Log::warning('StripeConnectPaymentService: PI retrieve failed', [
                'pi_id' => $booking->stripe_payment_intent_id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $statusMap = [
            'requires_payment_method' => 'pending',
            'requires_confirmation' => 'pending',
            'requires_action' => 'pending',
            'processing' => 'processing',
            'requires_capture' => 'authorized',
            'canceled' => 'cancelled',
            'succeeded' => 'captured',
        ];

        // UNE CAPTURE DE FRAIS N'EST PAS UN ENCAISSEMENT DE PRESTATION.
        if ($booking->payment_status === MissionPaymentService::STATUT_FRAIS_CAPTURES) {
            return;
        }

        $newStatus = $statusMap[$intent->status] ?? $booking->payment_status;

        $booking->forceFill([
            'payment_status' => $newStatus,
            'payment_captured_at' => $newStatus === 'captured'
                ? ($booking->payment_captured_at ?? now())
                : $booking->payment_captured_at,
        ])->save();
    }
}
