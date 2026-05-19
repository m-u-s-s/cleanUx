<?php

namespace App\Services\Promotion;

use App\Events\Promotion\ReferralQualified;
use App\Events\Promotion\ReferralRegistered;
use App\Events\Promotion\ReferralRewardGranted;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use App\Notifications\Promotion\ReferralRewardGrantedNotification;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralService
{
    public const DEFAULT_REFERRER_REWARD = 15.00;
    public const DEFAULT_REFEREE_REWARD = 10.00;
    public const REFERRAL_EXPIRY_DAYS = 90;
    public const REWARD_PROMO_EXPIRY_DAYS = 365;

    public function ensureReferralCode(User $user): string
    {
        if (! empty($user->referral_code)) {
            return $user->referral_code;
        }

        $code = $this->generateUniqueCode($user);

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function registerReferral(string $referralCode, User $referee, ?string $sourceChannel = null, ?string $ip = null): ?Referral
    {
        $code = strtoupper(trim($referralCode));
        if ($code === '') {
            return null;
        }

        $referrer = User::query()->where('referral_code', $code)->first();
        if (! $referrer) {
            return null;
        }

        if ((int) $referrer->id === (int) $referee->id) {
            return null;
        }

        $existing = Referral::query()
            ->where('referee_user_id', $referee->id)
            ->whereNotIn('status', [Referral::STATUS_EXPIRED, Referral::STATUS_FRAUD])
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($referrer, $referee, $code, $sourceChannel, $ip) {
            $referral = Referral::create([
                'referrer_user_id' => $referrer->id,
                'referee_user_id' => $referee->id,
                'referee_email' => $referee->email,
                'referral_code' => $code,
                'status' => Referral::STATUS_SIGNED_UP,
                'invited_at' => now(),
                'signed_up_at' => now(),
                'expires_at' => now()->addDays(self::REFERRAL_EXPIRY_DAYS),
                'referrer_reward_amount' => self::DEFAULT_REFERRER_REWARD,
                'referee_reward_amount' => self::DEFAULT_REFEREE_REWARD,
                'currency' => 'EUR',
                'source_channel' => $sourceChannel,
                'ip_signup' => $ip,
            ]);

            $referee->forceFill(['referred_by_referral_id' => $referral->id])->save();

            ActivityLogger::log('referral.signed_up', $referral, [
                'referrer_user_id' => $referrer->id,
                'referee_user_id' => $referee->id,
                'referral_code' => $code,
            ]);

            ReferralRegistered::dispatch($referral);

            return $referral;
        });
    }

    public function markQualifiedByBooking(Booking $booking): ?Referral
    {
        $refereeId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
        if (! $refereeId) {
            return null;
        }

        $referral = Referral::query()
            ->where('referee_user_id', $refereeId)
            ->whereIn('status', [Referral::STATUS_INVITED, Referral::STATUS_SIGNED_UP])
            ->first();

        if (! $referral || ! $referral->isQualifiable()) {
            return null;
        }

        if (! $booking->isCompleted()) {
            return null;
        }

        return DB::transaction(function () use ($referral, $booking) {
            $referral->update([
                'status' => Referral::STATUS_QUALIFIED,
                'qualifying_booking_id' => $booking->id,
                'qualified_at' => now(),
            ]);

            ActivityLogger::log('referral.qualified', $referral, [
                'qualifying_booking_id' => $booking->id,
            ]);

            ReferralQualified::dispatch($referral);

            $this->grantRewards($referral);

            $this->awardLoyaltyForReferral($referral);

            return $referral->fresh();
        });
    }

    protected function awardLoyaltyForReferral(Referral $referral): void
    {
        try {
            $referrer = User::find($referral->referrer_user_id);
            if (! $referrer) {
                return;
            }
            $points = (int) config('loyalty.earning.referral_qualified_bonus', 1000);

            app(\App\Services\Loyalty\LoyaltyService::class)->award(
                $referrer,
                \App\Models\LoyaltyTransaction::TYPE_EARN_REFERRAL,
                $points,
                $referral,
                'referral_qualified:' . $referral->id,
                'Parrainage qualifié — filleul ' . ($referral->referee_email ?? $referral->referee_user_id),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function grantRewards(Referral $referral): void
    {
        if ($referral->status !== Referral::STATUS_QUALIFIED) {
            return;
        }

        DB::transaction(function () use ($referral) {
            $this->grantSingleReward($referral, ReferralReward::ROLE_REFERRER, (float) $referral->referrer_reward_amount);
            $this->grantSingleReward($referral, ReferralReward::ROLE_REFEREE, (float) $referral->referee_reward_amount);

            $referral->update([
                'status' => Referral::STATUS_REWARDED,
                'rewarded_at' => now(),
            ]);

            ActivityLogger::log('referral.rewarded', $referral, [
                'referrer_user_id' => $referral->referrer_user_id,
                'referee_user_id' => $referral->referee_user_id,
                'referrer_reward_amount' => $referral->referrer_reward_amount,
                'referee_reward_amount' => $referral->referee_reward_amount,
            ]);
        });
    }

    public function statsForUser(User $user): array
    {
        $base = Referral::query()->forReferrer($user->id);

        return [
            'referral_code' => $this->ensureReferralCode($user),
            'total_invited' => (clone $base)->count(),
            'total_signed_up' => (clone $base)->whereIn('status', [
                Referral::STATUS_SIGNED_UP,
                Referral::STATUS_QUALIFIED,
                Referral::STATUS_REWARDED,
            ])->count(),
            'total_qualified' => (clone $base)->whereIn('status', [
                Referral::STATUS_QUALIFIED,
                Referral::STATUS_REWARDED,
            ])->count(),
            'total_rewarded' => (clone $base)->where('status', Referral::STATUS_REWARDED)->count(),
            'total_earned' => (float) ReferralReward::query()
                ->where('beneficiary_user_id', $user->id)
                ->where('role', ReferralReward::ROLE_REFERRER)
                ->whereIn('status', [
                    ReferralReward::STATUS_GRANTED,
                    ReferralReward::STATUS_CONSUMED,
                ])
                ->sum('amount'),
        ];
    }

    protected function grantSingleReward(Referral $referral, string $role, float $amount): ?ReferralReward
    {
        if ($amount <= 0) {
            return null;
        }

        $beneficiaryId = $role === ReferralReward::ROLE_REFERRER
            ? $referral->referrer_user_id
            : $referral->referee_user_id;

        if (! $beneficiaryId) {
            return null;
        }

        $expiresAt = now()->addDays(self::REWARD_PROMO_EXPIRY_DAYS);

        $promoCode = $this->buildRewardPromoCode($referral, $role, $amount, $beneficiaryId, $expiresAt);

        $reward = ReferralReward::create([
            'referral_id' => $referral->id,
            'beneficiary_user_id' => $beneficiaryId,
            'role' => $role,
            'reward_type' => ReferralReward::TYPE_PROMO_CODE,
            'amount' => $amount,
            'currency' => $referral->currency ?? 'EUR',
            'status' => ReferralReward::STATUS_GRANTED,
            'promo_code_id' => $promoCode->id,
            'granted_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        ReferralRewardGranted::dispatch($reward);

        try {
            $beneficiary = User::find($beneficiaryId);
            if ($beneficiary) {
                $beneficiary->notify(new ReferralRewardGrantedNotification($reward));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $reward;
    }

    protected function buildRewardPromoCode(Referral $referral, string $role, float $amount, int $beneficiaryId, \DateTimeInterface $expiresAt): PromoCode
    {
        $prefix = $role === ReferralReward::ROLE_REFERRER ? 'REFER' : 'WELCOME';

        do {
            $code = $prefix . strtoupper(Str::random(8));
        } while (PromoCode::where('code', $code)->exists());

        return PromoCode::create([
            'code' => $code,
            'name' => sprintf('Parrainage %s — %.2f €', $role, $amount),
            'description' => 'Récompense de parrainage (générée automatiquement)',
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => $amount,
            'max_total_uses' => 1,
            'max_uses_per_user' => 1,
            'audience_scope' => PromoCode::SCOPE_SPECIFIC,
            'allowed_user_ids' => [(int) $beneficiaryId],
            'issued_to_user_id' => $beneficiaryId,
            'status' => PromoCode::STATUS_ACTIVE,
            'source' => PromoCode::SOURCE_REFERRAL,
            'valid_from' => now(),
            'valid_until' => $expiresAt,
            'metadata' => [
                'referral_id' => $referral->id,
                'referral_role' => $role,
            ],
        ]);
    }

    protected function generateUniqueCode(User $user): string
    {
        $base = $this->slugify($user->name ?: 'CLEAN');
        $base = Str::upper(Str::limit($base, 6, ''));
        if ($base === '') {
            $base = 'CLEAN';
        }

        do {
            $candidate = $base . strtoupper(Str::random(4));
        } while (User::query()->where('referral_code', $candidate)->exists());

        return $candidate;
    }

    protected function slugify(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii($value))) ?? '';
    }
}
