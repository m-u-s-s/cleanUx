<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use Illuminate\Support\Carbon;

/**
 * Évalue le tier d'un account en fonction de ses period_points
 * (= somme des credits sur la fenêtre roulante config('loyalty.tier_period_days')).
 *
 * Le tier choisi est le plus haut dont `min_period_points` est <= period_points.
 */
class LoyaltyTierEvaluator
{
    public function evaluate(LoyaltyAccount $account): ?LoyaltyTier
    {
        $periodPoints = $this->computePeriodPoints($account);
        $tier = $this->tierForPoints($periodPoints);

        $account->forceFill([
            'period_points' => $periodPoints,
            'tier_evaluated_at' => now(),
        ])->save();

        return $tier;
    }

    public function computePeriodPoints(LoyaltyAccount $account): int
    {
        $periodDays = (int) config('loyalty.tier_period_days', 365);
        $from = Carbon::now()->subDays($periodDays);

        $credits = (int) LoyaltyTransaction::query()
            ->where('loyalty_account_id', $account->id)
            ->where('direction', LoyaltyTransaction::DIRECTION_CREDIT)
            ->withinPeriod($from)
            ->sum('points');

        $penalties = (int) LoyaltyTransaction::query()
            ->where('loyalty_account_id', $account->id)
            ->where('direction', LoyaltyTransaction::DIRECTION_DEBIT)
            ->where('type', LoyaltyTransaction::TYPE_PENALTY)
            ->withinPeriod($from)
            ->sum('points');

        return max(0, $credits - $penalties);
    }

    public function tierForPoints(int $points): ?LoyaltyTier
    {
        return LoyaltyTier::query()
            ->active()
            ->where('min_period_points', '<=', $points)
            ->orderByDesc('min_period_points')
            ->first();
    }
}
