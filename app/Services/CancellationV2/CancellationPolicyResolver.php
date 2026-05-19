<?php

namespace App\Services\CancellationV2;

use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyTier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Résout la policy + tier applicable pour un booking + actor + temps.
 *
 *   - Filtre par actor_role (client/provider/both)
 *   - Filtre par trade (trade specific wins, sinon catch-all)
 *   - Filtre par validity period
 *   - Tier match : min_hours_before ≤ hours < max_hours_before (max null = open-ended)
 */
class CancellationPolicyResolver
{
    public function resolveForBooking(int $bookingId, string $actorRole, int $hoursBefore, ?\DateTimeInterface $at = null): array
    {
        $tradeCode = $this->resolveTradeFromBooking($bookingId);

        $candidates = CancellationPolicy::query()
            ->active()
            ->get()
            ->filter(fn (CancellationPolicy $p) => $p->appliesToActor($actorRole)
                && $p->appliesToTrade($tradeCode)
                && $p->isWithinValidity($at));

        if ($candidates->isEmpty()) {
            return ['policy' => null, 'tier' => null];
        }

        // Score : trade-specific 10k + version, catch-all 1k + version
        $best = null;
        $bestScore = -1;
        foreach ($candidates as $policy) {
            $score = $policy->trade_codes && count($policy->trade_codes) > 0 ? 10000 : 1000;
            $score += (int) $policy->version;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $policy;
            }
        }

        if (! $best) {
            return ['policy' => null, 'tier' => null];
        }

        $tier = $this->resolveTier($best, $hoursBefore);

        return ['policy' => $best, 'tier' => $tier];
    }

    public function resolveTier(CancellationPolicy $policy, int $hoursBefore): ?CancellationPolicyTier
    {
        return $policy->tiers->first(fn (CancellationPolicyTier $t) => $t->matchesHoursBefore($hoursBefore));
    }

    public function resolveTradeFromBooking(int $bookingId): ?string
    {
        if (! Schema::hasTable('bookings')) {
            return null;
        }

        $row = DB::table('bookings')->where('id', $bookingId)->first();
        if (! $row || ! ($row->service_catalog_id ?? null)) {
            return null;
        }
        if (! Schema::hasTable('service_catalogs') || ! Schema::hasTable('trades')) {
            return null;
        }
        $service = DB::table('service_catalogs')->where('id', $row->service_catalog_id)->first();
        if (! $service || ! ($service->trade_id ?? null)) {
            return null;
        }
        $trade = DB::table('trades')->where('id', $service->trade_id)->first();
        return $trade->code ?? null;
    }
}
