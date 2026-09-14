<?php

namespace App\Services\CancellationV2;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\BookingInsurance;
use App\Models\CancellationAudit;
use App\Services\Insurance\InsuranceService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\ProviderWalletService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stripe\Refund;
use Stripe\Stripe;

/** CancellationIntegrationsRunner — side-effects after a cancellation is committed. */
class CancellationIntegrationsRunner
{
    public function run(BookingCancellationV2 $row): BookingCancellationV2
    {
        $integrationsCfg = (array) Config::get('cancellation_v2.integrations', []);
        $log = (array) ($row->integrations_log ?? []);

        /*
         * LES FRAIS SE PRENNENT MÊME QUAND IL N'Y A RIEN À REMBOURSER.
         *
         * La capture sur l'empreinte n'était atteignable QUE depuis `tryStripeRefund`, lui-même
         * gardé par `refund_amount_cents > 0 && refund_method === 'stripe'`. Or dès que les frais
         * valent 100 %, le remboursement vaut 0 et la méthode devient `none` : la garde était
         * fausse deux fois, et le cas le PLUS coûteux était exactement celui qui n'encaissait rien.
         */
        if (! empty($integrationsCfg['stripe_refund']) && ($capture = $this->capturerSurLEmpreinte($row))) {
            $log['stripe_fee_capture'] = $capture;
            CancellationAudit::create([
                'cancellation_id' => $row->id,
                'actor_user_id' => $row->cancelled_by_user_id,
                'action' => CancellationAudit::ACTION_REFUNDED,
                'after_state' => $capture,
                'occurred_at' => now(),
            ]);
        } elseif (! empty($integrationsCfg['stripe_refund']) && $row->refund_amount_cents > 0 && $row->refund_method === 'stripe') {
            try {
                $log['stripe_refund'] = $this->tryStripeRefund($row);
                CancellationAudit::create([
                    'cancellation_id' => $row->id,
                    'actor_user_id' => $row->cancelled_by_user_id,
                    'action' => CancellationAudit::ACTION_REFUNDED,
                    'after_state' => $log['stripe_refund'],
                    'occurred_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $log['stripe_refund_error'] = $e->getMessage();
                CancellationAudit::create([
                    'cancellation_id' => $row->id,
                    'actor_user_id' => $row->cancelled_by_user_id,
                    'action' => CancellationAudit::ACTION_REFUND_FAILED,
                    'notes' => $e->getMessage(),
                    'occurred_at' => now(),
                ]);
                Log::warning('CancellationEngine: stripe refund failed', [
                    'cancellation_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Loyalty forfeit (if module available)
        if (! empty($integrationsCfg['loyalty_forfeit'])
            && class_exists(LoyaltyService::class)) {
            try {
                $log['loyalty'] = ['notified' => true];
                // Hook : si la cancellation est ≤ window, on devrait forfeit les points
                // gagnés via cette booking. Skeleton call ici, à câbler selon flow exact.
            } catch (\Throwable $e) {
                $log['loyalty_error'] = $e->getMessage();
            }
        }

        // Promo restore
        if (! empty($integrationsCfg['promo_restore'])
            && Schema::hasTable('promo_code_redemptions')) {
            try {
                $restored = DB::table('promo_code_redemptions')
                    ->where('booking_id', $row->booking_id)
                    ->update(['status' => 'reversed', 'updated_at' => now()]);
                $log['promo_restore'] = ['rows_reversed' => $restored];
            } catch (\Throwable $e) {
                $log['promo_restore_error'] = $e->getMessage();
            }
        }

        // Insurance cancel (auto-cancel related insurance policies)
        if (! empty($integrationsCfg['insurance_cancel'])
            && class_exists(InsuranceService::class)
            && class_exists(BookingInsurance::class)) {
            try {
                $svc = app(InsuranceService::class);
                $insurances = BookingInsurance::query()
                    ->where('booking_id', $row->booking_id)
                    ->whereIn('status', [
                        BookingInsurance::STATUS_ACTIVE,
                        BookingInsurance::STATUS_PROPOSED,
                    ])
                    ->get();
                $cancelled = 0;
                foreach ($insurances as $insurance) {
                    $svc->cancel($insurance);
                    $cancelled++;
                }
                $log['insurance_cancel'] = ['cancelled_count' => $cancelled];
            } catch (\Throwable $e) {
                $log['insurance_cancel_error'] = $e->getMessage();
            }
        }

        $row->forceFill(['integrations_log' => $log])->save();

        return $row;
    }

    /** Effectue le refund réel via Stripe SDK. */
    /**
     * UNE EMPREINTE NON CAPTUREE NE SE REMBOURSE PAS : ON Y PREND LES FRAIS.
     * Rembourser un paiement seulement autorise ne rend rien et ne facture rien.
     *
     * @return array<string, mixed>|null
     */
    protected function capturerSurLEmpreinte(BookingCancellationV2 $row): ?array
    {
        if ($row->fee_amount_cents <= 0) {
            return null;
        }

        $booking = Booking::query()->find($row->booking_id);

        if (! $booking || $booking->payment_status !== 'authorized' || ! $booking->stripe_payment_intent_id) {
            return null;
        }

        try {
            app(MissionPaymentService::class)->capturerLesFraisDAnnulation($booking, (int) $row->fee_amount_cents);

            return ['status' => 'fee_captured', 'fee_amount_cents' => (int) $row->fee_amount_cents];
        } catch (\Throwable $e) {
            Log::error('[cancellation_v2] frais non encaisses sur l empreinte', [
                'booking_id' => $row->booking_id,
                'fee_amount_cents' => $row->fee_amount_cents,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'fee_capture_failed', 'error' => $e->getMessage()];
        }
    }

    protected function tryStripeRefund(BookingCancellationV2 $row): array
    {
        // La capture sur empreinte est traitée par `run()`, AVANT cette branche : les deux cas
        // restent exclusifs (on ne rembourse pas une empreinte, on y prend les frais).
        if ($row->refund_amount_cents <= 0) {
            return ['status' => 'no_refund', 'refund_amount_cents' => 0];
        }

        if (! class_exists(Refund::class)) {
            return [
                'status' => 'manual',
                'error' => 'stripe_sdk_unavailable',
                'refund_amount_cents' => $row->refund_amount_cents,
            ];
        }

        $booking = Booking::query()->find($row->booking_id);
        $paymentIntentId = $booking?->stripe_payment_intent_id ?? null;
        if (! $paymentIntentId) {
            return [
                'status' => 'manual',
                'error' => 'no_payment_intent',
                'refund_amount_cents' => $row->refund_amount_cents,
                'booking_id' => $row->booking_id,
            ];
        }

        $stripeSecret = (string) config('services.stripe.secret', '');
        if ($stripeSecret === '') {
            return ['status' => 'manual', 'error' => 'stripe_secret_missing'];
        }

        try {
            Stripe::setApiKey($stripeSecret);

            // Idempotency key sur (cancellation_id, refund_amount) pour éviter double-refund
            $idempotencyKey = 'cancel_v2_'.$row->id.'_'.$row->refund_amount_cents;

            $parametres = [
                'payment_intent' => $paymentIntentId,
                'amount' => (int) $row->refund_amount_cents,
                'reason' => 'requested_by_customer',
                // Stripe stocke les métadonnées en CHAÎNES : on convertit ici plutôt que de
                // laisser l'API le faire, comme `MissionPaymentService` le fait déjà.
                'metadata' => [
                    'cancellation_id' => (string) $row->id,
                    'booking_id' => (string) $row->booking_id,
                    'cancellation_reason' => (string) ($row->reason ?? ''),
                ],
            ];

            // La contrepartie réelle du clawback écrit vingt lignes plus bas : sans elle, le
            // remboursement sort du solde de la plateforme et le prestataire garde sa part.
            if ($booking->provider_amount_cents !== null) {
                $parametres['reverse_transfer'] = true;
            }

            $refund = Refund::create($parametres, [
                'idempotency_key' => $idempotencyKey,
            ]);

            // F9 fix: write a proportional wallet clawback so the provider does not
            // retain money the client was refunded. Mirrors the formula in
            // StripeConnectPaymentService::refundMissionPayment():
            //   clawbackCents = round(refundCents × providerCents / max(1, totalCents))
            // Soft-fail: a clawback failure must never prevent the Stripe refund
            // result from being returned (the refund already happened).
            try {
                $totalCents = max(1, (int) ($booking->payment_amount_cents ?? 0));
                $providerCents = (int) ($booking->provider_amount_cents ?? $booking->payment_amount_cents ?? 0);
                $refundedCents = (int) $row->refund_amount_cents;
                $clawbackCents = min((int) round($refundedCents * $providerCents / $totalCents), $providerCents);

                if ($clawbackCents > 0) {
                    app(ProviderWalletService::class)->recordRefundClawback(
                        $booking,
                        round((float) $clawbackCents / 100, 2),
                        $refund->id,
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[cancellation_v2] wallet clawback after refund failed', [
                    'cancellation_id' => $row->id,
                    'refund_id' => $refund->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'status' => 'succeeded',
                'refund_id' => $refund->id,
                'refund_amount_cents' => $row->refund_amount_cents,
                'currency' => $row->currency,
                'stripe_status' => $refund->status,
            ];
        } catch (\Throwable $e) {
            Log::warning('[cancellation_v2] stripe refund failed', [
                'cancellation_id' => $row->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'refund_amount_cents' => $row->refund_amount_cents,
            ];
        }
    }
}
