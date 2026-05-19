<?php

namespace App\Services\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use Carbon\Carbon;

class CycleGenerator
{
    /**
     * Crée (idempotent) le prochain cycle pour une subscription.
     * Renvoie le cycle créé ou existant.
     */
    public function generateNextCycle(SubscriptionV2 $sub, ?Carbon $forceStartAt = null): SubscriptionCycleV2
    {
        $nextNumber = (int) $sub->billing_cycle_count + 1;

        $existing = SubscriptionCycleV2::query()
            ->where('subscription_id', $sub->id)
            ->where('cycle_number', $nextNumber)
            ->first();
        if ($existing) {
            return $existing;
        }

        $periodDays = (int) ($sub->plan?->periodDays() ?? 30);
        $start = $forceStartAt ?: ($sub->current_cycle_end ?: $sub->started_at ?: now());
        if (! ($start instanceof Carbon)) {
            $start = Carbon::parse($start);
        }
        $end = $start->copy()->addDays($periodDays);

        return SubscriptionCycleV2::query()->create([
            'subscription_id' => $sub->id,
            'cycle_number' => $nextNumber,
            'period_start' => $start,
            'period_end' => $end,
            'planned_amount_cents' => $sub->plan?->price_cents ?? 0,
            'billing_status' => SubscriptionCycleV2::STATUS_PENDING,
        ]);
    }

    /**
     * Met à jour les dates de cycle courant + next_billing_at sur la subscription
     * après création / paiement d'un cycle.
     */
    public function advanceSubscriptionWindows(SubscriptionV2 $sub, SubscriptionCycleV2 $cycle): void
    {
        $sub->update([
            'current_cycle_start' => $cycle->period_start,
            'current_cycle_end' => $cycle->period_end,
            'next_billing_at' => $cycle->period_end,
        ]);
    }
}
