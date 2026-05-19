<?php

namespace App\Services\SubscriptionsV2;

use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use App\Models\SubscriptionsV2\SubscriptionV2;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionEngine
{
    public function __construct(
        protected CycleGenerator $cycles,
        protected BillingProcessor $billing,
    ) {}

    /**
     * Crée une nouvelle subscription idempotent par (user, plan, status=active|trialing).
     * Un user peut avoir plusieurs subscriptions actives de plans DIFFÉRENTS.
     */
    public function subscribe(User $user, SubscriptionPlanV2 $plan, array $opts = []): SubscriptionV2
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan' => ['Plan inactif.']]);
        }
        $currency = (string) ($opts['currency'] ?? $plan->currency ?? config('subscriptions_v2.default_currency', 'EUR'));
        if (! in_array($currency, (array) config('subscriptions_v2.allowed_currencies', []), true)) {
            throw ValidationException::withMessages(['currency' => ['Devise non supportée.']]);
        }

        // Idempotency : si une subscription active de ce plan existe déjà pour ce user → retournée
        $existing = SubscriptionV2::query()
            ->where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->whereIn('status', [SubscriptionV2::STATUS_TRIALING, SubscriptionV2::STATUS_ACTIVE])
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $plan, $opts, $currency) {
            $trialDays = (int) ($opts['trial_days'] ?? $plan->trial_days);
            $providerUserId = $opts['provider_user_id'] ?? null;
            $now = now();

            $sub = SubscriptionV2::query()->create([
                'code' => SubscriptionV2::generateCode(),
                'plan_id' => $plan->id,
                'user_id' => $user->id,
                'provider_user_id' => $providerUserId,
                'status' => $trialDays > 0 ? SubscriptionV2::STATUS_TRIALING : SubscriptionV2::STATUS_ACTIVE,
                'started_at' => $now,
                'trial_ends_at' => $trialDays > 0 ? $now->copy()->addDays($trialDays) : null,
                'billing_currency' => $currency,
                'metadata' => (array) ($opts['metadata'] ?? []),
            ]);

            // First cycle (uses trial_end as start if trialing, sinon now)
            $startAt = $trialDays > 0 ? $sub->trial_ends_at : $sub->started_at;
            $cycle = $this->cycles->generateNextCycle($sub, $startAt);
            $this->cycles->advanceSubscriptionWindows($sub, $cycle);

            return $sub->fresh();
        });
    }

    public function pause(SubscriptionV2 $sub): SubscriptionV2
    {
        if (! $sub->isUsable()) {
            throw ValidationException::withMessages(['subscription' => ['Subscription non active.']]);
        }
        $sub->update([
            'status' => SubscriptionV2::STATUS_PAUSED,
            'paused_at' => now(),
        ]);
        return $sub->fresh();
    }

    public function resume(SubscriptionV2 $sub): SubscriptionV2
    {
        if ($sub->status !== SubscriptionV2::STATUS_PAUSED) {
            throw ValidationException::withMessages(['subscription' => ['Subscription non en pause.']]);
        }
        $sub->update([
            'status' => SubscriptionV2::STATUS_ACTIVE,
            'paused_at' => null,
        ]);
        return $sub->fresh();
    }

    /**
     * Cancel : immediate=true → arrête tout de suite, sinon cancel_at_period_end.
     */
    public function cancel(SubscriptionV2 $sub, bool $immediate = false): SubscriptionV2
    {
        if ($sub->isCancelled()) {
            return $sub;
        }
        if ($immediate) {
            $sub->update([
                'status' => SubscriptionV2::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'ends_at' => now(),
                'cancel_at_period_end' => false,
            ]);
        } else {
            $sub->update([
                'cancel_at_period_end' => true,
                'ends_at' => $sub->current_cycle_end ?: $sub->next_billing_at,
            ]);
        }
        return $sub->fresh();
    }

    /**
     * Change plan en cours de subscription. Méthode conservatrice :
     * - Active la nouvelle subscription au prochain cycle (upgrade/downgrade
     *   pas proratisé — à enrichir Phase 2 si besoin).
     */
    public function changePlan(SubscriptionV2 $sub, SubscriptionPlanV2 $newPlan): SubscriptionV2
    {
        if (! $newPlan->is_active) {
            throw ValidationException::withMessages(['plan' => ['Plan cible inactif.']]);
        }
        if (! $sub->isUsable()) {
            throw ValidationException::withMessages(['subscription' => ['Subscription non active.']]);
        }
        $sub->update([
            'plan_id' => $newPlan->id,
            'metadata' => array_merge((array) $sub->metadata, [
                'last_plan_change_at' => now()->toIso8601String(),
                'previous_plan_code' => $sub->plan?->code,
            ]),
        ]);
        return $sub->fresh();
    }

    /**
     * Tick d'un cycle : appelé par cron / job. Génère le prochain cycle si nécessaire
     * et déclenche le billing si due.
     */
    public function tick(SubscriptionV2 $sub): SubscriptionV2
    {
        // Stoppe si cancel_at_period_end et next_billing_at passé
        if ($sub->cancel_at_period_end && $sub->ends_at && $sub->ends_at->isPast()) {
            $sub->update([
                'status' => SubscriptionV2::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
            return $sub->fresh();
        }
        if (! $sub->isUsable() && $sub->status !== SubscriptionV2::STATUS_PAST_DUE) {
            return $sub;
        }
        if ($sub->status === SubscriptionV2::STATUS_TRIALING && $sub->trial_ends_at && $sub->trial_ends_at->isPast()) {
            $sub->update(['status' => SubscriptionV2::STATUS_ACTIVE]);
        }

        // Cycle courant arrivé à terme ? → générer next + bill
        if ($sub->next_billing_at && $sub->next_billing_at->isPast()) {
            $cycle = $this->cycles->generateNextCycle($sub);
            $this->billing->processCycle($cycle);
            $this->cycles->advanceSubscriptionWindows($sub->fresh(), $cycle);
        }

        return $sub->fresh();
    }
}
