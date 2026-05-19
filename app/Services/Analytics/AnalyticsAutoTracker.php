<?php

namespace App\Services\Analytics;

use App\Events\Kyc\KycCompleted;
use App\Events\Loyalty\LoyaltyPointsAwarded;
use App\Events\Promotion\PromoCodeRedeemed;
use App\Events\Rating\RatingPublished;
use Illuminate\Events\Dispatcher;

/**
 * Subscriber Laravel qui écoute les typed events existants des autres modules
 * et les traduit en events analytics standardisés.
 *
 * Soft-fail : si AnalyticsService échoue, le flow business n'est pas affecté
 * (try/catch dans le service).
 */
class AnalyticsAutoTracker
{
    public function __construct(protected AnalyticsService $analytics)
    {
    }

    public function onRatingPublished(RatingPublished $event): void
    {
        $feedback = $event->feedback ?? null;
        if (! $feedback) {
            return;
        }

        $this->analytics->track('rating.published', [
            'feedback_id' => $feedback->id,
            'rating' => $feedback->rating ?? null,
            'booking_id' => $feedback->rendez_vous_id ?? null,
        ], [
            'user' => $feedback->author_user ?? null,
            'idempotency_key' => 'rating.published:' . $feedback->id,
        ]);
    }

    public function onPromoCodeRedeemed(PromoCodeRedeemed $event): void
    {
        $redemption = $event->redemption ?? null;
        if (! $redemption) {
            return;
        }

        $this->analytics->track('promo.redeemed', [
            'redemption_id' => $redemption->id,
            'code' => $redemption->promoCode?->code,
            'discount_cents' => $redemption->discount_cents ?? null,
            'booking_id' => $redemption->booking_id ?? null,
        ], [
            'user' => $redemption->user ?? null,
            'revenue_cents' => isset($redemption->discount_cents) ? -1 * (int) $redemption->discount_cents : null,
            'currency' => $redemption->currency ?? null,
            'idempotency_key' => 'promo.redeemed:' . $redemption->id,
        ]);
    }

    public function onLoyaltyPointsAwarded(LoyaltyPointsAwarded $event): void
    {
        $transaction = $event->transaction ?? null;
        if (! $transaction) {
            return;
        }

        $this->analytics->track('loyalty.points_awarded', [
            'transaction_id' => $transaction->id,
            'points' => $transaction->points ?? null,
            'reason' => $transaction->reason ?? null,
        ], [
            'user' => $transaction->account?->user ?? null,
            'idempotency_key' => 'loyalty.points_awarded:' . $transaction->id,
        ]);
    }

    public function onKycCompleted(KycCompleted $event): void
    {
        $verification = $event->verification ?? null;
        if (! $verification) {
            return;
        }

        $this->analytics->track('kyc.completed', [
            'verification_id' => $verification->id,
            'decision' => $verification->decision ?? null,
            'provider' => $verification->provider ?? null,
        ], [
            'user' => $verification->user ?? null,
            'idempotency_key' => 'kyc.completed:' . $verification->id,
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            RatingPublished::class => 'onRatingPublished',
            PromoCodeRedeemed::class => 'onPromoCodeRedeemed',
            LoyaltyPointsAwarded::class => 'onLoyaltyPointsAwarded',
            KycCompleted::class => 'onKycCompleted',
        ];
    }
}
