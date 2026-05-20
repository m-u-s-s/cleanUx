<?php

namespace App\Services\Badges;

use App\Models\Booking;
use App\Models\Feedback;
use App\Models\ProviderBadge;
use App\Models\ProviderBadgeAward;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Engine d'évaluation/attribution des badges providers.
 *
 * Workflow :
 *  - evaluate(user) : check tous les badges actifs et award ceux atteints
 *  - awardBadge(user, badge) : idempotent par UNIQUE(provider_user_id, badge_id)
 *
 * Critères supportés :
 *  - missions_count : count bookings 'termine' par employe_id
 *  - rating_avg : moyenne des feedbacks client_to_provider (>= threshold)
 *  - tips_received : count tips charged/paid_out reçus
 *  - tenure_days : ancienneté en jours depuis users.created_at
 *  - loyalty_points : lifetime_points du provider sur loyalty_accounts
 *  - streak_5stars : nombre consécutif de 5★ récents
 */
class ProviderBadgeEngine
{
    public function evaluate(User $provider): array
    {
        $awarded = [];
        $badges = ProviderBadge::query()->where('is_active', true)->get();

        foreach ($badges as $badge) {
            try {
                $value = $this->resolveValue($provider, $badge);
                if ($value === null) {
                    continue;
                }
                if ($value < $badge->threshold) {
                    continue;
                }

                $award = $this->awardBadge($provider, $badge, $value);
                if ($award) {
                    $awarded[] = $award;
                }
            } catch (\Throwable $e) {
                Log::warning('[badges] evaluate failed', [
                    'user_id' => $provider->id,
                    'badge_code' => $badge->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $awarded;
    }

    public function awardBadge(User $provider, ProviderBadge $badge, ?int $value = null): ?ProviderBadgeAward
    {
        // Idempotent via UNIQUE constraint — firstOrCreate
        return DB::transaction(function () use ($provider, $badge, $value) {
            $existing = ProviderBadgeAward::query()
                ->where('provider_user_id', $provider->id)
                ->where('badge_id', $badge->id)
                ->first();
            if ($existing) {
                return null;   // déjà attribué, idempotent
            }

            return ProviderBadgeAward::query()->create([
                'provider_user_id' => $provider->id,
                'badge_id' => $badge->id,
                'value_at_award' => $value,
                'awarded_at' => now(),
            ]);
        });
    }

    protected function resolveValue(User $provider, ProviderBadge $badge): ?int
    {
        return match ($badge->criterion_type) {
            ProviderBadge::CRITERION_MISSIONS_COUNT => $this->countMissionsCompleted($provider),
            ProviderBadge::CRITERION_RATING_AVG => $this->ratingAvgScaled($provider),
            ProviderBadge::CRITERION_TIPS_RECEIVED => $this->countTipsReceived($provider),
            ProviderBadge::CRITERION_TENURE_DAYS => $this->tenureDays($provider),
            ProviderBadge::CRITERION_LOYALTY_POINTS => $this->loyaltyLifetimePoints($provider),
            ProviderBadge::CRITERION_STREAK_5_STARS => $this->streakFiveStars($provider),
            default => null,
        };
    }

    protected function countMissionsCompleted(User $provider): int
    {
        return Booking::query()
            ->where(function ($q) use ($provider) {
                $q->where('employe_id', $provider->id)
                  ->orWhere('assigned_employee_id', $provider->id);
            })
            ->whereIn('status', ['termine', 'completed', 'closed'])
            ->count();
    }

    /**
     * Retourne la moyenne des ratings × 100 (pour stocker en int).
     * 4.5★ → 450.
     */
    protected function ratingAvgScaled(User $provider): int
    {
        $avg = (float) Feedback::query()
            ->where('employe_id', $provider->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->avg('rating');

        return (int) round($avg * 100);
    }

    protected function countTipsReceived(User $provider): int
    {
        if (! Schema::hasTable('booking_tips')) {
            return 0;
        }
        return (int) DB::table('booking_tips')
            ->where('provider_user_id', $provider->id)
            ->whereIn('status', ['charged', 'paid_out'])
            ->count();
    }

    protected function tenureDays(User $provider): int
    {
        if (! $provider->created_at) {
            return 0;
        }
        return (int) $provider->created_at->diffInDays(now());
    }

    protected function loyaltyLifetimePoints(User $provider): int
    {
        if (! Schema::hasTable('loyalty_accounts')) {
            return 0;
        }
        $points = DB::table('loyalty_accounts')
            ->where('user_id', $provider->id)
            ->value('lifetime_points');

        return (int) ($points ?? 0);
    }

    /**
     * Compte les 5★ consécutifs récents (les N derniers feedbacks ordonnés).
     * Renvoie le streak courant à partir du plus récent.
     */
    protected function streakFiveStars(User $provider): int
    {
        $recent = Feedback::query()
            ->where('employe_id', $provider->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->orderByDesc('id')
            ->limit(50)
            ->pluck('rating');

        $streak = 0;
        foreach ($recent as $r) {
            if ((int) $r === 5) {
                $streak++;
            } else {
                break;
            }
        }
        return $streak;
    }
}
