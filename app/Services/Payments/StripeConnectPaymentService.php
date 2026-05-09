<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

/**
 * Phase 13 — Service complet de paiements Stripe Connect.
 *
 * Étend MissionPaymentService (qui gère seulement authorize/capture/markFailed).
 *
 * Ajoute :
 *   - captureMissionPayment(): version "complete + capture + create payout entry"
 *   - refundMissionPayment(): refund total ou partiel
 *   - syncPaymentIntent(): re-sync depuis Stripe vers booking (utile post-webhook)
 *   - createProviderPayout(): crée l'entrée comptable côté plateforme
 *
 * NB : Stripe gère automatiquement le transfer vers le compte Connect via
 * `transfer_data.destination` posé par MissionPaymentService::authorize().
 * Notre ProviderPayout est une trace comptable côté CleanUx, pas un transfer
 * actif.
 */
class StripeConnectPaymentService
{
    public function __construct()
    {
        if ($key = config('cashier.secret')) {
            Stripe::setApiKey($key);
        }
    }

    /**
     * Capture le PaymentIntent d'une mission terminée et crée une entrée
     * ProviderPayout.
     *
     * Appelé typiquement depuis MissionLifecycleService::completeMission()
     * ou en async via un job.
     *
     * Retourne le ProviderPayout créé, ou null si pas de payment_intent à capturer.
     */
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
                'mission_id'      => $mission->id,
                'payment_status'  => $booking->payment_status,
            ]);
            return null;
        }

        return DB::transaction(function () use ($mission, $booking) {
            try {
                $intent = PaymentIntent::retrieve($booking->stripe_payment_intent_id);
                $intent->capture();
            } catch (\Throwable $e) {
                Log::error('StripeConnectPaymentService: capture failed', [
                    'mission_id' => $mission->id,
                    'pi_id'      => $booking->stripe_payment_intent_id,
                    'error'      => $e->getMessage(),
                ]);

                $booking->update([
                    'payment_status'    => 'failed',
                    'payment_failed_at' => now(),
                ]);
                throw new RuntimeException('Capture échouée : ' . $e->getMessage(), 0, $e);
            }

            $booking->update([
                'payment_status'      => 'captured',
                'payment_captured_at' => now(),
            ]);

            // Créer l'entrée ProviderPayout (entrée comptable côté CleanUx)
            $payout = $this->createProviderPayout($mission, $booking);

            Log::info('StripeConnectPaymentService: capture + payout entry OK', [
                'mission_id'  => $mission->id,
                'payout_id'   => $payout->id,
                'amount'      => $payout->amount,
            ]);

            return $payout;
        });
    }

    /**
     * Crée une entrée ProviderPayout pour une mission capturée.
     *
     * Le montant est calculé depuis booking.provider_amount_cents si dispo,
     * sinon depuis mission.client_price - mission.platform_commission.
     */
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
            'provider_user_id'         => $providerUserId,
            'provider_organization_id' => optional($mission->lead_provider_user)->current_organization_id,
            'amount'                   => $amount,
            'currency'                 => $currency,
            'status'                   => ProviderPayout::STATUS_PENDING,
            'provider'                 => 'stripe_connect',
            'period_start'             => now()->startOfDay()->toDateString(),
            'period_end'               => now()->endOfDay()->toDateString(),
            'metadata'                 => [
                'mission_id'              => $mission->id,
                'booking_id'              => $booking->id,
                'booking_reference'       => $booking->booking_reference,
                'stripe_payment_intent_id'=> $booking->stripe_payment_intent_id,
                'platform_fee_cents'      => $booking->platform_fee_cents,
                'provider_amount_cents'   => $booking->provider_amount_cents,
            ],
        ]);
    }

    /**
     * Refund total ou partiel d'un paiement déjà capturé.
     *
     * @param Booking $booking
     * @param int|null $amountCents Montant à refund. null = total.
     * @param string|null $reason 'requested_by_customer' | 'duplicate' | 'fraudulent'
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
                'error'      => $e->getMessage(),
            ]);
            throw new RuntimeException('Refund échoué : ' . $e->getMessage(), 0, $e);
        }

        $isTotal = $amountCents === null || $amountCents >= ($booking->payment_amount_cents ?? 0);

        $booking->update([
            'payment_status'   => $isTotal ? 'refunded' : 'partially_refunded',
            'payment_refunded_at' => now(),
        ]);

        Log::info('StripeConnectPaymentService: refund OK', [
            'booking_id' => $booking->id,
            'refund_id'  => $refund->id,
            'amount'     => $refund->amount,
            'is_total'   => $isTotal,
        ]);

        return $refund;
    }

    /**
     * Re-synchronise l'état du PaymentIntent depuis Stripe vers le booking.
     * Utile après réception d'un webhook pour s'assurer que la DB est à jour.
     */
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
            'requires_confirmation'   => 'pending',
            'requires_action'         => 'pending',
            'processing'              => 'processing',
            'requires_capture'        => 'authorized',
            'canceled'                => 'cancelled',
            'succeeded'               => 'captured',
        ];

        $newStatus = $statusMap[$intent->status] ?? $booking->payment_status;

        $booking->update([
            'payment_status' => $newStatus,
            'payment_captured_at' => $newStatus === 'captured'
                ? ($booking->payment_captured_at ?? now())
                : $booking->payment_captured_at,
        ]);
    }
}
