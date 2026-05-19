<?php

namespace App\Services\Loyalty;

use App\Events\Loyalty\LoyaltyPointsAwarded;
use App\Events\Loyalty\LoyaltyTierUpgraded;
use App\Events\Loyalty\LoyaltyTierDowngraded;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Notifications\Loyalty\LoyaltyTierChangedNotification;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function __construct(protected LoyaltyTierEvaluator $evaluator)
    {
    }

    public function accountFor(User $user): LoyaltyAccount
    {
        $account = LoyaltyAccount::query()->where('user_id', $user->id)->first();
        if ($account) {
            return $account;
        }

        $account = LoyaltyAccount::create([
            'user_id' => $user->id,
            'lifetime_points' => 0,
            'period_points' => 0,
            'redeemable_points' => 0,
            'points_period_started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $tier = $this->evaluator->tierForPoints(0);
        if ($tier) {
            $account->forceFill([
                'current_tier_id' => $tier->id,
                'tier_started_at' => now(),
                'tier_evaluated_at' => now(),
            ])->save();
        }

        return $account->fresh();
    }

    /**
     * Awarde des points à un utilisateur, idempotent via `idempotency_key`.
     *
     * @param  string  $type        Une des constantes LoyaltyTransaction::TYPE_*
     * @param  int     $points      Nombre de points (positif)
     * @param  Model|null $source    Modèle source pour traçabilité
     * @param  string  $idempotencyKey  Clé unique pour éviter doubles award
     */
    public function award(
        User $user,
        string $type,
        int $points,
        ?Model $source = null,
        string $idempotencyKey = '',
        ?string $reason = null,
        ?User $actor = null,
    ): ?LoyaltyTransaction {
        if (! Config::get('loyalty.enabled', true)) {
            return null;
        }

        if ($points <= 0) {
            return null;
        }

        if ($idempotencyKey === '') {
            $idempotencyKey = $this->buildIdempotencyKey($user->id, $type, $source);
        }

        // Idempotency check
        $existing = LoyaltyTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $type, $points, $source, $idempotencyKey, $reason, $actor) {
            $account = $this->accountFor($user);

            // Apply tier multiplier
            $multiplier = $account->tierMultiplier();
            $adjustedPoints = (int) round($points * $multiplier);

            $balanceBefore = (int) $account->lifetime_points;

            $tx = LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'user_id' => $user->id,
                'type' => $type,
                'direction' => LoyaltyTransaction::DIRECTION_CREDIT,
                'points' => $adjustedPoints,
                'balance_after' => $balanceBefore + $adjustedPoints,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'reason' => $reason,
                'actor_user_id' => $actor?->id,
                'occurred_at' => now(),
                'metadata' => [
                    'base_points' => $points,
                    'multiplier' => $multiplier,
                ],
            ]);

            $account->increment('lifetime_points', $adjustedPoints);
            $account->forceFill(['last_activity_at' => now()])->save();

            ActivityLogger::log('loyalty.points_awarded', $tx, [
                'user_id' => $user->id,
                'points' => $adjustedPoints,
                'type' => $type,
            ]);

            LoyaltyPointsAwarded::dispatch($tx);

            $this->reevaluateAndNotify($account->fresh(), $user);

            return $tx;
        });
    }

    public function adminAdjust(User $user, int $deltaPoints, User $admin, string $reason): LoyaltyTransaction
    {
        $account = $this->accountFor($user);

        $direction = $deltaPoints >= 0
            ? LoyaltyTransaction::DIRECTION_CREDIT
            : LoyaltyTransaction::DIRECTION_DEBIT;

        $abs = abs($deltaPoints);

        return DB::transaction(function () use ($account, $user, $deltaPoints, $abs, $direction, $admin, $reason) {
            $tx = LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'user_id' => $user->id,
                'type' => LoyaltyTransaction::TYPE_ADMIN_ADJUST,
                'direction' => $direction,
                'points' => $abs,
                'balance_after' => $direction === LoyaltyTransaction::DIRECTION_CREDIT
                    ? $account->lifetime_points + $abs
                    : max(0, $account->lifetime_points - $abs),
                'idempotency_key' => 'admin_adjust:' . uniqid('', true),
                'reason' => $reason,
                'actor_user_id' => $admin->id,
                'occurred_at' => now(),
            ]);

            if ($direction === LoyaltyTransaction::DIRECTION_CREDIT) {
                $account->increment('lifetime_points', $abs);
            } else {
                $account->decrement('lifetime_points', min($abs, $account->lifetime_points));
            }

            ActivityLogger::log('loyalty.admin_adjusted', $tx, [
                'user_id' => $user->id,
                'admin_user_id' => $admin->id,
                'delta' => $deltaPoints,
            ]);

            $this->reevaluateAndNotify($account->fresh(), $user);

            return $tx;
        });
    }

    public function reevaluateAndNotify(LoyaltyAccount $account, User $user): void
    {
        $previousTierId = $account->current_tier_id;
        $newTier = $this->evaluator->evaluate($account);

        if (! $newTier) {
            return;
        }

        if ((int) $previousTierId === (int) $newTier->id) {
            return;
        }

        $previousTier = $previousTierId
            ? \App\Models\LoyaltyTier::find($previousTierId)
            : null;

        $isUpgrade = $previousTier === null
            || $newTier->rank > $previousTier->rank;

        $account->forceFill([
            'current_tier_id' => $newTier->id,
            'tier_started_at' => now(),
        ])->save();

        ActivityLogger::log(
            $isUpgrade ? 'loyalty.tier_upgraded' : 'loyalty.tier_downgraded',
            $account->fresh(),
            [
                'user_id' => $user->id,
                'from_tier' => $previousTier?->slug,
                'to_tier' => $newTier->slug,
            ]
        );

        if ($isUpgrade) {
            LoyaltyTierUpgraded::dispatch($account->fresh(), $previousTier, $newTier);
        } else {
            LoyaltyTierDowngraded::dispatch($account->fresh(), $previousTier, $newTier);
        }

        try {
            $user->notify(new LoyaltyTierChangedNotification(
                $account->fresh(),
                $previousTier,
                $newTier,
                $isUpgrade,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function awardBookingPoints(User $user, $booking): ?LoyaltyTransaction
    {
        $amount = (float) ($booking->devis_estime ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $pointsPerEuro = (float) Config::get('loyalty.earning.points_per_euro_spent', 10);
        $points = (int) round($amount * $pointsPerEuro);

        $bookingId = $booking->id ?? null;

        return $this->award(
            $user,
            LoyaltyTransaction::TYPE_EARN_BOOKING,
            $points,
            $booking,
            'booking_completed:' . $bookingId,
            sprintf('Mission %s — %.2f €', $booking->booking_reference ?? "#{$bookingId}", $amount),
        );
    }

    protected function buildIdempotencyKey(int $userId, string $type, ?Model $source): string
    {
        $sourceKey = $source ? get_class($source) . ':' . $source->getKey() : 'none';
        return sprintf('%s:%d:%s', $type, $userId, $sourceKey);
    }
}
