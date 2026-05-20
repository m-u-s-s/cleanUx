<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoyaltyRedemptionService
{
    /**
     * Tente une redemption : check eligibility + stock + points → débite + crée row.
     * Soft-fail si module loyalty absent ou points insuffisants.
     */
    public function redeem(User $user, LoyaltyReward $reward, array $opts = []): LoyaltyRedemption
    {
        if (! $reward->is_active) {
            throw ValidationException::withMessages(['reward' => ['Récompense indisponible.']]);
        }

        // Stock check
        if (! $reward->isInStock()) {
            throw ValidationException::withMessages(['stock' => ['Stock épuisé.']]);
        }

        // Tier check (si LoyaltyService existant)
        if ($reward->min_tier_level > 0) {
            $userTierLevel = $this->resolveUserTierLevel($user);
            if ($userTierLevel < $reward->min_tier_level) {
                throw ValidationException::withMessages([
                    'tier' => ['Niveau de fidélité insuffisant pour cette récompense.'],
                ]);
            }
        }

        // Points balance check
        $balance = $this->resolveUserPointsBalance($user);
        if ($balance < $reward->points_cost) {
            throw ValidationException::withMessages([
                'points' => ["Points insuffisants (vous avez {$balance}, requis {$reward->points_cost})."],
            ]);
        }

        return DB::transaction(function () use ($user, $reward, $opts) {
            // Stock decrement (avec lock pour éviter race condition)
            if ($reward->stock_remaining !== null) {
                $locked = LoyaltyReward::query()->lockForUpdate()->find($reward->id);
                if ($locked->stock_remaining <= 0) {
                    throw ValidationException::withMessages(['stock' => ['Stock épuisé.']]);
                }
                $locked->decrement('stock_remaining');
            }

            // Débit points (via LoyaltyService si dispo)
            $this->debitPoints($user, $reward->points_cost, $reward);

            // Détermine voucher_code selon reward_type
            $voucherCode = match ($reward->reward_type) {
                LoyaltyReward::TYPE_DISCOUNT_CODE,
                LoyaltyReward::TYPE_PARTNER_VOUCHER => strtoupper('CLX-' . Str::random(10)),
                default => null,
            };
            $deliveryMethod = match ($reward->reward_type) {
                LoyaltyReward::TYPE_DISCOUNT_CODE,
                LoyaltyReward::TYPE_PARTNER_VOUCHER => 'email_code',
                LoyaltyReward::TYPE_PHYSICAL_ITEM => 'postal',
                LoyaltyReward::TYPE_SERVICE_CREDIT => 'in_app_credit',
                default => 'manual',
            };

            return LoyaltyRedemption::query()->create([
                'code' => LoyaltyRedemption::generateCode(),
                'user_id' => $user->id,
                'reward_id' => $reward->id,
                'points_spent' => $reward->points_cost,
                'status' => $deliveryMethod === 'email_code'
                    ? LoyaltyRedemption::STATUS_CONFIRMED
                    : LoyaltyRedemption::STATUS_PENDING,
                'delivery_method' => $deliveryMethod,
                'voucher_code' => $voucherCode,
                'shipping_address' => $opts['shipping_address'] ?? null,
                'confirmed_at' => $deliveryMethod === 'email_code' ? now() : null,
                'metadata' => $opts['metadata'] ?? null,
            ]);
        });
    }

    public function cancel(LoyaltyRedemption $redemption, string $reason): LoyaltyRedemption
    {
        if (! $redemption->isCancellable()) {
            throw ValidationException::withMessages(['redemption' => ['Non annulable dans cet état.']]);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => ['Raison minimum 5 caractères.']]);
        }

        return DB::transaction(function () use ($redemption, $reason) {
            // Re-créditer les points
            $this->creditPointsBack($redemption->user, $redemption->points_spent, $redemption->reward);

            // Restock si applicable
            $reward = LoyaltyReward::query()->lockForUpdate()->find($redemption->reward_id);
            if ($reward && $reward->stock_remaining !== null) {
                $reward->increment('stock_remaining');
            }

            $redemption->update([
                'status' => LoyaltyRedemption::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            return $redemption->fresh();
        });
    }

    public function markDelivered(LoyaltyRedemption $redemption): LoyaltyRedemption
    {
        if ($redemption->status === LoyaltyRedemption::STATUS_DELIVERED) {
            return $redemption;
        }
        $redemption->update([
            'status' => LoyaltyRedemption::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
        return $redemption->fresh();
    }

    protected function resolveUserPointsBalance(User $user): int
    {
        // Loyalty v1 actuel : loyalty_accounts.redeemable_points
        if (Schema::hasTable('loyalty_accounts')) {
            $balance = DB::table('loyalty_accounts')
                ->where('user_id', $user->id)
                ->value('redeemable_points');
            return (int) ($balance ?? 0);
        }
        // Fallback (table de test legacy)
        if (Schema::hasTable('loyalty_balances')) {
            $balance = DB::table('loyalty_balances')
                ->where('user_id', $user->id)
                ->value('points_balance');
            return (int) ($balance ?? 0);
        }
        return 0;
    }

    protected function resolveUserTierLevel(User $user): int
    {
        if (Schema::hasTable('loyalty_accounts') && Schema::hasTable('loyalty_tiers')) {
            $tierSlug = DB::table('loyalty_accounts')
                ->leftJoin('loyalty_tiers', 'loyalty_accounts.current_tier_id', '=', 'loyalty_tiers.id')
                ->where('loyalty_accounts.user_id', $user->id)
                ->value('loyalty_tiers.slug');
            return match ($tierSlug) {
                'platinum' => 3,
                'gold' => 2,
                'silver' => 1,
                default => 0,
            };
        }
        if (Schema::hasTable('loyalty_balances')) {
            $tier = DB::table('loyalty_balances')
                ->where('user_id', $user->id)
                ->value('current_tier_code');
            return match ($tier) {
                'platinum' => 3,
                'gold' => 2,
                'silver' => 1,
                default => 0,
            };
        }
        return 0;
    }

    protected function debitPoints(User $user, int $points, LoyaltyReward $reward): void
    {
        try {
            $loyaltyService = app(\App\Services\Loyalty\LoyaltyService::class);
            if (method_exists($loyaltyService, 'awardPoints')) {
                $loyaltyService->awardPoints($user, -$points, 'redemption.' . $reward->code, [
                    'reward_id' => $reward->id,
                    'reward_code' => $reward->code,
                ]);
            } else {
                // Fallback : update balance direct
                $this->updateBalanceFallback($user, -$points);
            }
        } catch (\Throwable $e) {
            Log::warning('[loyalty_redemption] debit failed, fallback', ['error' => $e->getMessage()]);
            $this->updateBalanceFallback($user, -$points);
        }
    }

    protected function creditPointsBack(User $user, int $points, LoyaltyReward $reward): void
    {
        try {
            $loyaltyService = app(\App\Services\Loyalty\LoyaltyService::class);
            if (method_exists($loyaltyService, 'awardPoints')) {
                $loyaltyService->awardPoints($user, $points, 'redemption.refund.' . $reward->code, [
                    'reward_id' => $reward->id,
                ]);
                return;
            }
        } catch (\Throwable) {}
        $this->updateBalanceFallback($user, $points);
    }

    protected function updateBalanceFallback(User $user, int $delta): void
    {
        if (Schema::hasTable('loyalty_accounts')) {
            $exists = DB::table('loyalty_accounts')->where('user_id', $user->id)->exists();
            if (! $exists) {
                DB::table('loyalty_accounts')->insert([
                    'user_id' => $user->id,
                    'redeemable_points' => max(0, $delta),
                    'lifetime_points' => 0,
                    'period_points' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return;
            }
            DB::table('loyalty_accounts')
                ->where('user_id', $user->id)
                ->update([
                    'redeemable_points' => DB::raw("MAX(0, CAST(COALESCE(redeemable_points, 0) AS INTEGER) + ({$delta}))"),
                    'updated_at' => now(),
                ]);
            return;
        }
        if (Schema::hasTable('loyalty_balances')) {
            DB::table('loyalty_balances')
                ->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'points_balance' => DB::raw("COALESCE(points_balance, 0) + ({$delta})"),
                        'updated_at' => now(),
                    ],
                );
        }
    }
}
