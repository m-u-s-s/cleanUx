<?php

namespace App\Services\Promotion;

use App\Events\Promotion\PromoCodeRedeemed;
use App\Models\Booking;
use App\Models\PromoCampaign;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use App\Notifications\Promotion\PromoCodeAppliedNotification;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PromoCodeService
{
    public const ERROR_NOT_FOUND = 'not_found';
    public const ERROR_NOT_ACTIVE = 'not_active';
    public const ERROR_OUTSIDE_WINDOW = 'outside_window';
    public const ERROR_CAMPAIGN_INACTIVE = 'campaign_inactive';
    public const ERROR_CAMPAIGN_BUDGET = 'campaign_budget_exhausted';
    public const ERROR_GLOBAL_LIMIT = 'global_limit_reached';
    public const ERROR_USER_LIMIT = 'user_limit_reached';
    public const ERROR_MIN_AMOUNT = 'min_amount_not_reached';
    public const ERROR_FIRST_BOOKING_ONLY = 'first_booking_only';
    public const ERROR_AUDIENCE = 'audience_mismatch';
    public const ERROR_TRADE = 'trade_not_allowed';
    public const ERROR_SERVICE = 'service_not_allowed';
    public const ERROR_COUNTRY = 'country_not_allowed';
    public const ERROR_ZONE = 'zone_not_allowed';
    public const ERROR_USER_NOT_ALLOWED = 'user_not_allowed';
    public const ERROR_ISSUED_TO_OTHER = 'issued_to_other_user';

    public function validate(string $rawCode, PromoCodeValidationContext $context): PromoCodeValidationResult
    {
        $code = strtoupper(trim($rawCode));

        if ($code === '') {
            return PromoCodeValidationResult::fail(self::ERROR_NOT_FOUND, 'Code promo vide.');
        }

        /** @var PromoCode|null $promo */
        $promo = PromoCode::query()->forCode($code)->first();

        if (! $promo) {
            return PromoCodeValidationResult::fail(self::ERROR_NOT_FOUND, "Code promo introuvable.");
        }

        if ($promo->status !== PromoCode::STATUS_ACTIVE) {
            return PromoCodeValidationResult::fail(self::ERROR_NOT_ACTIVE, "Ce code promo n'est pas actif.", $promo);
        }

        if (! $promo->isWithinValidityWindow()) {
            return PromoCodeValidationResult::fail(self::ERROR_OUTSIDE_WINDOW, "Ce code promo est expiré ou pas encore valable.", $promo);
        }

        if ($promo->campaign) {
            if (! $promo->campaign->isRunning()) {
                return PromoCodeValidationResult::fail(self::ERROR_CAMPAIGN_INACTIVE, "La campagne associée n'est plus active.", $promo);
            }
            if (! $promo->campaign->hasBudgetRemaining()) {
                return PromoCodeValidationResult::fail(self::ERROR_CAMPAIGN_BUDGET, "Le budget de cette campagne est épuisé.", $promo);
            }
        }

        if (! $promo->hasGlobalUsesLeft()) {
            return PromoCodeValidationResult::fail(self::ERROR_GLOBAL_LIMIT, "Ce code a atteint son nombre maximum d'utilisations.", $promo);
        }

        if (! $promo->hasUserUsesLeft($context->user->id)) {
            return PromoCodeValidationResult::fail(self::ERROR_USER_LIMIT, "Vous avez déjà utilisé ce code le nombre maximum de fois.", $promo);
        }

        if ($promo->issued_to_user_id && (int) $promo->issued_to_user_id !== (int) $context->user->id) {
            return PromoCodeValidationResult::fail(self::ERROR_ISSUED_TO_OTHER, "Ce code est nominatif et réservé à un autre utilisateur.", $promo);
        }

        if ($promo->min_booking_amount !== null && $context->bookingAmount < (float) $promo->min_booking_amount) {
            return PromoCodeValidationResult::fail(
                self::ERROR_MIN_AMOUNT,
                sprintf("Montant minimum requis : %.2f %s.", (float) $promo->min_booking_amount, $context->currency),
                $promo
            );
        }

        if ($promo->first_booking_only && ! $context->isFirstBooking) {
            return PromoCodeValidationResult::fail(self::ERROR_FIRST_BOOKING_ONLY, "Ce code est réservé à la première réservation.", $promo);
        }

        if (! $this->matchesAudience($promo, $context)) {
            return PromoCodeValidationResult::fail(self::ERROR_AUDIENCE, "Ce code n'est pas disponible pour votre profil.", $promo);
        }

        if (! $this->matchesAllowList($promo->allowed_trade_ids, $context->tradeId)) {
            return PromoCodeValidationResult::fail(self::ERROR_TRADE, "Ce code ne s'applique pas à ce métier.", $promo);
        }
        if (! $this->matchesAllowList($promo->allowed_service_catalog_ids, $context->serviceCatalogId)) {
            return PromoCodeValidationResult::fail(self::ERROR_SERVICE, "Ce code ne s'applique pas à ce service.", $promo);
        }
        if (! $this->matchesAllowList($promo->allowed_country_ids, $context->countryId)) {
            return PromoCodeValidationResult::fail(self::ERROR_COUNTRY, "Ce code n'est pas disponible dans ce pays.", $promo);
        }
        if (! $this->matchesAllowList($promo->allowed_zone_ids, $context->serviceZoneId)) {
            return PromoCodeValidationResult::fail(self::ERROR_ZONE, "Ce code n'est pas disponible dans cette zone.", $promo);
        }
        if (! $this->matchesAllowList($promo->allowed_user_ids, $context->user->id)) {
            return PromoCodeValidationResult::fail(self::ERROR_USER_NOT_ALLOWED, "Vous n'êtes pas autorisé à utiliser ce code.", $promo);
        }

        $discount = $this->calculateDiscount($promo, $context->bookingAmount);
        $final = max(0, round($context->bookingAmount - $discount, 2));

        return PromoCodeValidationResult::ok($promo, $discount, $final);
    }

    public function calculateDiscount(PromoCode $promo, float $amount): float
    {
        $amount = max(0.0, $amount);

        $discount = match ($promo->discount_type) {
            PromoCode::TYPE_PERCENT => $amount * ((float) $promo->discount_value / 100),
            PromoCode::TYPE_FIXED => (float) $promo->discount_value,
            PromoCode::TYPE_FREE_FIRST => $amount,
            default => 0.0,
        };

        if ($promo->max_discount_amount !== null) {
            $discount = min($discount, (float) $promo->max_discount_amount);
        }

        return round(min($discount, $amount), 2);
    }

    public function apply(PromoCode $promo, User $user, Booking $booking, float $bookingAmount): PromoCodeRedemption
    {
        return DB::transaction(function () use ($promo, $user, $booking, $bookingAmount) {
            $promo = PromoCode::query()->whereKey($promo->id)->lockForUpdate()->first();

            if (! $promo->hasGlobalUsesLeft()) {
                throw new \RuntimeException('Promo code reached global usage limit during apply().');
            }
            if (! $promo->hasUserUsesLeft($user->id)) {
                throw new \RuntimeException('Promo code reached per-user usage limit during apply().');
            }

            $discount = $this->calculateDiscount($promo, $bookingAmount);
            $finalAmount = max(0, round($bookingAmount - $discount, 2));

            $redemption = PromoCodeRedemption::create([
                'promo_code_id' => $promo->id,
                'user_id' => $user->id,
                'booking_id' => $booking->id,
                'status' => PromoCodeRedemption::STATUS_APPLIED,
                'booking_amount_before' => round($bookingAmount, 2),
                'discount_amount' => $discount,
                'booking_amount_after' => $finalAmount,
                'currency' => $booking->currency ?? 'EUR',
                'redeemed_at' => now(),
                'metadata' => [
                    'discount_type' => $promo->discount_type,
                    'discount_value' => (float) $promo->discount_value,
                ],
            ]);

            $promo->increment('total_uses');

            if ($promo->promo_campaign_id) {
                PromoCampaign::query()->whereKey($promo->promo_campaign_id)->update([
                    'total_redemptions' => DB::raw('total_redemptions + 1'),
                    'total_discounted' => DB::raw('total_discounted + ' . (float) $discount),
                ]);
            }

            ActivityLogger::log('promo_code.applied', $booking, [
                'promo_code_id' => $promo->id,
                'promo_code' => $promo->code,
                'user_id' => $user->id,
                'discount_amount' => $discount,
                'booking_id' => $booking->id,
            ]);

            PromoCodeRedeemed::dispatch($redemption);

            try {
                $user->notify(new PromoCodeAppliedNotification($redemption));
            } catch (\Throwable $e) {
                // notifications sont best-effort, ne doivent pas casser le booking
                report($e);
            }

            return $redemption;
        });
    }

    public function revert(PromoCodeRedemption $redemption, string $reason): void
    {
        if ($redemption->status === PromoCodeRedemption::STATUS_REVERTED) {
            return;
        }

        DB::transaction(function () use ($redemption, $reason) {
            $promo = PromoCode::query()->whereKey($redemption->promo_code_id)->lockForUpdate()->first();

            $redemption->update([
                'status' => PromoCodeRedemption::STATUS_REVERTED,
                'reverted_at' => now(),
                'reverted_reason' => $reason,
            ]);

            if ($promo) {
                $promo->decrement('total_uses');

                if ($promo->promo_campaign_id) {
                    PromoCampaign::query()->whereKey($promo->promo_campaign_id)->update([
                        'total_redemptions' => DB::raw('GREATEST(total_redemptions - 1, 0)'),
                        'total_discounted' => DB::raw('GREATEST(total_discounted - ' . (float) $redemption->discount_amount . ', 0)'),
                    ]);
                }
            }

            ActivityLogger::log('promo_code.reverted', $redemption->booking ?? $redemption, [
                'promo_code_id' => $redemption->promo_code_id,
                'reason' => $reason,
            ]);
        });
    }

    protected function matchesAudience(PromoCode $promo, PromoCodeValidationContext $context): bool
    {
        return match ($promo->audience_scope) {
            PromoCode::SCOPE_ALL, null => true,
            PromoCode::SCOPE_NEW => $context->isFirstBooking,
            PromoCode::SCOPE_RETURNING => ! $context->isFirstBooking,
            PromoCode::SCOPE_B2B => $context->isB2B,
            PromoCode::SCOPE_SPECIFIC => ! empty($promo->allowed_user_ids)
                && in_array($context->user->id, (array) $promo->allowed_user_ids, false),
            default => true,
        };
    }

    /**
     * @param  array<int>|null  $allowed
     */
    protected function matchesAllowList(?array $allowed, ?int $candidate): bool
    {
        if (empty($allowed)) {
            return true;
        }
        if ($candidate === null) {
            return false;
        }

        return in_array($candidate, array_map('intval', $allowed), true);
    }
}
