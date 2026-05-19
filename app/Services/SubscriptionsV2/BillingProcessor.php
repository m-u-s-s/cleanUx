<?php

namespace App\Services\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionInvoiceV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Services\SubscriptionsV2\Contracts\BillingProviderContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingProcessor
{
    public function __construct(protected BillingProviderContract $provider) {}

    /**
     * Process a single cycle : génère invoice → tente charge → update statuts.
     * Idempotent : si déjà paid, return existing.
     */
    public function processCycle(SubscriptionCycleV2 $cycle): SubscriptionCycleV2
    {
        if ($cycle->isPaid()) {
            return $cycle;
        }

        $sub = $cycle->subscription;
        // Past_due est CHARGEABLE (retry). Seul paused/cancelled/expired skip.
        $skipStatuses = [
            SubscriptionV2::STATUS_PAUSED,
            SubscriptionV2::STATUS_CANCELLED,
            SubscriptionV2::STATUS_EXPIRED,
        ];
        if (! $sub || in_array($sub->status, $skipStatuses, true)) {
            $cycle->update([
                'billing_status' => SubscriptionCycleV2::STATUS_SKIPPED,
                'last_error' => 'subscription_not_chargeable_' . ($sub?->status ?? 'missing'),
            ]);
            return $cycle->fresh();
        }

        // 1) Ensure invoice exists
        $invoice = $this->ensureInvoice($cycle);

        // 2) Charge via provider (soft-fail wrap)
        $result = $this->provider->chargeCycle($cycle);

        return DB::transaction(function () use ($cycle, $invoice, $sub, $result) {
            if ($result->success) {
                $invoice->update([
                    'status' => SubscriptionInvoiceV2::STATUS_PAID,
                    'paid_at' => now(),
                    'stripe_invoice_id' => $result->reference,
                    'payload' => $result->toArray(),
                    'last_error' => null,
                ]);
                $cycle->update([
                    'billing_status' => SubscriptionCycleV2::STATUS_PAID,
                    'billed_amount_cents' => $result->amountCents,
                    'billed_at' => now(),
                    'invoice_id' => $invoice->id,
                    'billing_raw' => $result->toArray(),
                    'last_error' => null,
                ]);
                $sub->update([
                    'billing_cycle_count' => $sub->billing_cycle_count + 1,
                    'total_billed_cents' => $sub->total_billed_cents + $result->amountCents,
                    'consecutive_failed_charges' => 0,
                    'status' => $sub->status === SubscriptionV2::STATUS_PAST_DUE
                        ? SubscriptionV2::STATUS_ACTIVE
                        : ($sub->status === SubscriptionV2::STATUS_TRIALING
                            ? SubscriptionV2::STATUS_ACTIVE
                            : $sub->status),
                ]);
                return $cycle->fresh();
            }

            // failure path
            $invoice->update([
                'status' => SubscriptionInvoiceV2::STATUS_FAILED,
                'last_error' => $result->error,
                'payload' => $result->toArray(),
            ]);
            $cycle->update([
                'billing_status' => SubscriptionCycleV2::STATUS_FAILED,
                'last_error' => $result->error,
                'billing_raw' => $result->toArray(),
                'invoice_id' => $invoice->id,
            ]);

            $failed = $sub->consecutive_failed_charges + 1;
            $maxFailed = (int) config('subscriptions_v2.max_consecutive_failed_charges', 4);
            $autoCancelDays = (int) config('subscriptions_v2.auto_cancel_after_failed_days', 15);
            $shouldAutoCancel = $failed >= $maxFailed;

            $sub->update([
                'status' => $shouldAutoCancel
                    ? SubscriptionV2::STATUS_CANCELLED
                    : SubscriptionV2::STATUS_PAST_DUE,
                'consecutive_failed_charges' => $failed,
                'cancelled_at' => $shouldAutoCancel ? now() : $sub->cancelled_at,
                'ends_at' => $shouldAutoCancel ? now() : $sub->ends_at,
            ]);

            if ($shouldAutoCancel) {
                Log::info('[subscriptions_v2] auto-cancelled after consecutive failures', [
                    'subscription_id' => $sub->id, 'failures' => $failed,
                ]);
            }

            return $cycle->fresh();
        });
    }

    protected function ensureInvoice(SubscriptionCycleV2 $cycle): SubscriptionInvoiceV2
    {
        $existing = SubscriptionInvoiceV2::query()
            ->where('subscription_id', $cycle->subscription_id)
            ->where('cycle_id', $cycle->id)
            ->first();
        if ($existing) {
            return $existing;
        }
        return SubscriptionInvoiceV2::query()->create([
            'code' => SubscriptionInvoiceV2::generateCode(),
            'subscription_id' => $cycle->subscription_id,
            'cycle_id' => $cycle->id,
            'amount_cents' => $cycle->planned_amount_cents,
            'currency' => $cycle->subscription->billing_currency,
            'status' => SubscriptionInvoiceV2::STATUS_OPEN,
            'due_at' => $cycle->period_start,
        ]);
    }
}
