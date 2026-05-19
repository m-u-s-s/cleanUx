<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\StripeReconciliationRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Payout;
use Stripe\Stripe;

/**
 * Compare Stripe ↔ DB locale et remonte les écarts.
 *
 * Scopes :
 *   - payment_intents : PIs Stripe sur la période vs Booking.payment_status
 *   - payouts : Payouts Stripe vs ProviderPayout local
 *
 * Le but n'est PAS de corriger automatiquement (risque). Le but est de
 * lister les écarts pour qu'un admin les traite manuellement, sauf cas
 * triviaux (auto_fixed).
 */
class StripeReconciliationService
{
    public function __construct()
    {
        if ($key = config('cashier.secret')) {
            Stripe::setApiKey($key);
        }
    }

    public function run(
        string $scope = StripeReconciliationRun::SCOPE_ALL,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?User $triggeredBy = null,
    ): StripeReconciliationRun {
        $from ??= now()->subDays(7)->startOfDay();
        $to ??= now()->endOfDay();

        $run = StripeReconciliationRun::create([
            'scope' => $scope,
            'period_start' => $from->toDateString(),
            'period_end' => $to->toDateString(),
            'status' => StripeReconciliationRun::STATUS_RUNNING,
            'started_at' => now(),
            'triggered_by_user_id' => $triggeredBy?->id,
        ]);

        $mismatches = [];
        $itemsChecked = 0;
        $requiresAttention = 0;

        try {
            if (in_array($scope, [StripeReconciliationRun::SCOPE_PAYMENT_INTENTS, StripeReconciliationRun::SCOPE_ALL], true)) {
                [$piChecked, $piMismatches] = $this->reconcilePaymentIntents($from, $to);
                $itemsChecked += $piChecked;
                $mismatches = array_merge($mismatches, $piMismatches);
            }

            if (in_array($scope, [StripeReconciliationRun::SCOPE_PAYOUTS, StripeReconciliationRun::SCOPE_ALL], true)) {
                [$poChecked, $poMismatches] = $this->reconcilePayouts($from, $to);
                $itemsChecked += $poChecked;
                $mismatches = array_merge($mismatches, $poMismatches);
            }

            $requiresAttention = count(array_filter($mismatches, fn ($m) => ($m['severity'] ?? 'warning') === 'error'));

            $run->update([
                'status' => StripeReconciliationRun::STATUS_COMPLETED,
                'items_checked' => $itemsChecked,
                'mismatches_found' => count($mismatches),
                'requires_attention' => $requiresAttention,
                'mismatches' => $mismatches,
                'summary' => [
                    'by_severity' => collect($mismatches)->groupBy('severity')->map->count()->all(),
                    'by_type' => collect($mismatches)->groupBy('type')->map->count()->all(),
                ],
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => StripeReconciliationRun::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('StripeReconciliation: run failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $run->fresh();
    }

    /**
     * @return array{0:int, 1:array}
     */
    protected function reconcilePaymentIntents(Carbon $from, Carbon $to): array
    {
        if (! $this->stripeApiAvailable()) {
            return [0, [$this->mismatch(
                'stripe_unavailable',
                'payment_intents',
                null,
                'Stripe API not configured — skipping PI reconciliation',
                'warning'
            )]];
        }

        $mismatches = [];
        $itemsChecked = 0;

        try {
            $intents = PaymentIntent::all([
                'created' => [
                    'gte' => $from->getTimestamp(),
                    'lte' => $to->getTimestamp(),
                ],
                'limit' => 100,
            ]);

            foreach ($intents->data as $intent) {
                $itemsChecked++;
                $booking = Booking::query()
                    ->where('stripe_payment_intent_id', $intent->id)
                    ->first();

                if (! $booking) {
                    $mismatches[] = $this->mismatch(
                        'payment_intent_not_in_db',
                        'payment_intents',
                        $intent->id,
                        "PaymentIntent {$intent->id} (statut {$intent->status}) absent de la DB locale",
                        'error'
                    );
                    continue;
                }

                $expectedStatus = $this->mapPiStatusToBookingStatus($intent->status);
                if ($expectedStatus && $booking->payment_status !== $expectedStatus) {
                    $mismatches[] = $this->mismatch(
                        'payment_status_mismatch',
                        'payment_intents',
                        $intent->id,
                        sprintf(
                            "Booking #%d : status local=%s vs Stripe=%s (PI %s)",
                            $booking->id,
                            $booking->payment_status ?? '?',
                            $expectedStatus,
                            $intent->status
                        ),
                        'error',
                        ['booking_id' => $booking->id, 'expected' => $expectedStatus, 'local' => $booking->payment_status]
                    );
                }
            }
        } catch (\Throwable $e) {
            $mismatches[] = $this->mismatch('stripe_api_error', 'payment_intents', null, $e->getMessage(), 'error');
        }

        return [$itemsChecked, $mismatches];
    }

    /**
     * @return array{0:int, 1:array}
     */
    protected function reconcilePayouts(Carbon $from, Carbon $to): array
    {
        if (! $this->stripeApiAvailable()) {
            return [0, [$this->mismatch(
                'stripe_unavailable',
                'payouts',
                null,
                'Stripe API not configured — skipping payout reconciliation',
                'warning'
            )]];
        }

        $mismatches = [];
        $itemsChecked = 0;

        try {
            $payouts = Payout::all([
                'created' => [
                    'gte' => $from->getTimestamp(),
                    'lte' => $to->getTimestamp(),
                ],
                'limit' => 100,
            ]);

            foreach ($payouts->data as $payout) {
                $itemsChecked++;
                $local = ProviderPayout::query()
                    ->where('provider_payout_id', $payout->id)
                    ->first();

                if (! $local && $payout->status === 'paid') {
                    $mismatches[] = $this->mismatch(
                        'stripe_payout_orphan',
                        'payouts',
                        $payout->id,
                        "Payout Stripe {$payout->id} (paid, montant {$payout->amount}) sans entrée locale",
                        'warning'
                    );
                    continue;
                }

                if ($local && $payout->status === 'paid' && $local->status !== ProviderPayout::STATUS_PAID) {
                    $mismatches[] = $this->mismatch(
                        'payout_status_drift',
                        'payouts',
                        $payout->id,
                        sprintf(
                            "Payout #%d local=%s vs Stripe=paid",
                            $local->id,
                            $local->status
                        ),
                        'error',
                        ['payout_id' => $local->id, 'local_status' => $local->status]
                    );
                }
            }
        } catch (\Throwable $e) {
            $mismatches[] = $this->mismatch('stripe_api_error', 'payouts', null, $e->getMessage(), 'error');
        }

        return [$itemsChecked, $mismatches];
    }

    protected function mismatch(
        string $type,
        string $scope,
        ?string $stripeId,
        string $message,
        string $severity = 'warning',
        array $extra = [],
    ): array {
        return array_merge([
            'type' => $type,
            'scope' => $scope,
            'stripe_id' => $stripeId,
            'message' => $message,
            'severity' => $severity,
            'detected_at' => now()->toIso8601String(),
        ], $extra);
    }

    protected function mapPiStatusToBookingStatus(string $piStatus): ?string
    {
        return [
            'requires_payment_method' => 'pending',
            'requires_confirmation' => 'pending',
            'requires_action' => 'pending',
            'processing' => 'processing',
            'requires_capture' => 'authorized',
            'canceled' => 'cancelled',
            'succeeded' => 'captured',
        ][$piStatus] ?? null;
    }

    protected function stripeApiAvailable(): bool
    {
        return ! empty(config('cashier.secret')) || ! empty(env('STRIPE_SECRET'));
    }
}
