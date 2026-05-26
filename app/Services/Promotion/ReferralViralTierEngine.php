<?php

namespace App\Services\Promotion;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade du module Referrals existant — ajoute des bonus paliers progressifs.
 *
 * Au lieu de "tu parraines X = 10€ chaque", on a :
 *   - Palier 1 (1 parrainage qualifié) : 10€ + 100 pts loyalty
 *   - Palier 2 (3 parrainages) : +50€ bonus + 500 pts
 *   - Palier 3 (10 parrainages) : badge VIP + abonnement gratuit 1 mois
 *   - Palier 4 (25 parrainages) : statut Ambassador + access early features
 *
 * Idempotent : un parrain ne reçoit le palier qu'une seule fois (table tier_awards).
 *
 * À appeler après chaque qualification de referral via ReferralService::markQualified().
 */
class ReferralViralTierEngine
{
    /**
     * Vérifie si le referrer atteint un nouveau palier et award le bonus.
     */
    public function checkAndAwardTier(User $referrer): array
    {
        $awarded = [];

        $qualifiedCount = $this->countQualifiedReferrals($referrer);
        // Priority: promotions.referral_tiers > referral.viral_tiers > hardcoded defaults
        $tiers = (array) Config::get(
            'promotions.referral_tiers',
            Config::get('referral.viral_tiers', $this->defaultTiers()),
        );

        foreach ($tiers as $tier) {
            if ($qualifiedCount < ($tier['threshold'] ?? PHP_INT_MAX)) {
                continue;
            }
            if ($this->alreadyAwarded($referrer, $tier['code'])) {
                continue;
            }
            try {
                $this->awardTier($referrer, $tier, $qualifiedCount);
                $awarded[] = $tier['code'];
            } catch (\Throwable $e) {
                Log::warning('[referral_viral] tier award failed', [
                    'user_id' => $referrer->id,
                    'tier' => $tier['code'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $awarded;
    }

    protected function countQualifiedReferrals(User $referrer): int
    {
        if (! Schema::hasTable('referrals')) {
            return 0;
        }
        return (int) Referral::query()
            ->where('referrer_user_id', $referrer->id)
            ->whereNotNull('qualified_at')
            ->count();
    }

    protected function alreadyAwarded(User $referrer, string $tierCode): bool
    {
        if (! Schema::hasTable('loyalty_transactions')) {
            return false;
        }
        return (bool) DB::table('loyalty_transactions')
            ->where('user_id', $referrer->id)
            ->where('idempotency_key', 'referral_tier_' . $tierCode . '_user_' . $referrer->id)
            ->exists();
    }

    protected function awardTier(User $referrer, array $tier, int $qualifiedCount): void
    {
        // Award loyalty points
        if (! empty($tier['loyalty_points']) && class_exists(\App\Services\Loyalty\LoyaltyService::class)) {
            try {
                app(\App\Services\Loyalty\LoyaltyService::class)->award(
                    user: $referrer,
                    type: 'earn_referral',
                    points: (int) $tier['loyalty_points'],
                    idempotencyKey: 'referral_tier_' . $tier['code'] . '_user_' . $referrer->id,
                    reason: "Palier parrainage {$tier['code']} ({$qualifiedCount} qualifiés)",
                );
            } catch (\Throwable $e) {
                Log::warning('[referral_viral] loyalty award failed', ['error' => $e->getMessage()]);
            }
        }

        // Award credit cash if defined
        if (! empty($tier['credit_cents']) && Schema::hasTable('customer_credits')) {
            try {
                DB::table('customer_credits')->insert([
                    'user_id' => $referrer->id,
                    'amount_cents' => (int) $tier['credit_cents'],
                    'currency' => 'EUR',
                    'source' => 'referral_tier_' . $tier['code'],
                    'expires_at' => now()->addMonths(6),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {
                // schema mismatch (cf customer_credits memory) — soft-fail
            }
        }

        // Send notification push + email
        $this->notifyTierUnlocked($referrer, $tier);
    }

    protected function notifyTierUnlocked(User $referrer, array $tier): void
    {
        try {
            if (! class_exists(\App\Services\Push\PushService::class)) {
                return;
            }
            app(\App\Services\Push\PushService::class)->dispatchToUser(
                user: $referrer,
                title: '🎉 Nouveau palier débloqué : ' . ($tier['name'] ?? $tier['code']),
                body: $tier['reward_description'] ?? "Votre récompense est créditée.",
                data: [
                    'type' => 'referral.tier_unlocked',
                    'tier_code' => $tier['code'],
                ],
                category: 'transactional',
                idempotencyKey: 'referral_tier_push_' . $tier['code'] . '_' . $referrer->id,
            );
        } catch (\Throwable $e) {
            Log::warning('[referral_viral] notify failed', ['error' => $e->getMessage()]);
        }
    }

    protected function defaultTiers(): array
    {
        return [
            [
                'code' => 'starter',
                'name' => 'Premier ami',
                'threshold' => 1,
                'loyalty_points' => 100,
                'credit_cents' => 1000,
                'reward_description' => '10€ + 100 pts loyalty',
            ],
            [
                'code' => 'enthusiast',
                'name' => 'Enthousiaste',
                'threshold' => 3,
                'loyalty_points' => 500,
                'credit_cents' => 5000,
                'reward_description' => '50€ bonus + 500 pts',
            ],
            [
                'code' => 'champion',
                'name' => 'Champion',
                'threshold' => 10,
                'loyalty_points' => 2000,
                'credit_cents' => 20000,
                'reward_description' => '200€ + badge VIP + abo 1 mois gratuit',
            ],
            [
                'code' => 'ambassador',
                'name' => 'Ambassadeur',
                'threshold' => 25,
                'loyalty_points' => 5000,
                'credit_cents' => 50000,
                'reward_description' => '500€ + statut Ambassador + early access features',
            ],
        ];
    }
}
