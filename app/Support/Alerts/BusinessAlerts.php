<?php

namespace App\Support\Alerts;

use App\Events\BusinessAlertRaised;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderPayout;

/**
 * Named static emitters for money-path business alert events.
 *
 * Each method dispatches exactly ONE BusinessAlertRaised event.
 * The Sentry wiring lives in a listener (BusinessAlertSentryListener), keeping
 * this class testable without a real Sentry connection:
 *
 *   Event::fake([BusinessAlertRaised::class]);
 *   BusinessAlerts::payoutFailed($payout);
 *   Event::assertDispatched(BusinessAlertRaised::class, fn($e) => $e->key === 'payout_failed');
 *
 * Register the listener in App\Providers\EventServiceProvider:
 *   BusinessAlertRaised::class => [BusinessAlertSentryListener::class],
 */
class BusinessAlerts
{
    /**
     * A booking's payment capture failed (e.g. card declined after hold).
     */
    public static function paymentCaptureFailed(Booking $booking): void
    {
        event(new BusinessAlertRaised(
            level: 'critical',
            key: 'payment_capture_failed',
            message: "Payment capture failed for booking #{$booking->id}",
            context: [
                'booking_id' => $booking->id,
                'client_id' => $booking->client_id ?? null,
                'amount' => $booking->total ?? $booking->price ?? null,
                'currency' => $booking->currency ?? null,
            ],
        ));
    }

    /**
     * A provider payout attempt failed (Stripe payout.failed webhook).
     */
    public static function payoutFailed(ProviderPayout $payout): void
    {
        event(new BusinessAlertRaised(
            level: 'critical',
            key: 'payout_failed',
            message: "Provider payout failed for payout #{$payout->id}",
            context: [
                'payout_id' => $payout->id,
                'provider_user_id' => $payout->provider_user_id ?? null,
                'amount' => $payout->amount,
                'currency' => $payout->currency ?? 'EUR',
                'provider_payout_id' => $payout->provider_payout_id ?? null,
            ],
        ));
    }

    /**
     * Outbound webhook delivery queue depth has exceeded a safe threshold.
     */
    public static function webhookBacklog(int $count): void
    {
        event(new BusinessAlertRaised(
            level: 'critical',
            key: 'webhook_backlog',
            message: "Outbound webhook backlog is high: {$count} pending deliveries",
            context: [
                'count' => $count,
            ],
        ));
    }

    /**
     * A mission has been running abnormally long while a payment hold is active.
     */
    public static function stuckMissionHoldingFunds(Mission $mission): void
    {
        event(new BusinessAlertRaised(
            level: 'critical',
            key: 'stuck_mission_holding_funds',
            message: "Mission #{$mission->id} appears stuck while holding customer funds",
            context: [
                'mission_id' => $mission->id,
                'status' => $mission->status ?? null,
                'booking_id' => $mission->booking_id ?? null,
                'planned_start_at' => isset($mission->planned_start_at)
                    ? (string) $mission->planned_start_at
                    : null,
            ],
        ));
    }

    /**
     * A Stripe↔DB reconciliation run found a divergence between amounts.
     *
     * @param  array<string, mixed>  $detail  e.g. ['stripe_total'=>…, 'db_total'=>…, 'delta'=>…]
     */
    public static function reconciliationDivergence(array $detail): void
    {
        event(new BusinessAlertRaised(
            level: 'critical',
            key: 'reconciliation_divergence',
            message: 'Stripe↔DB reconciliation divergence detected',
            context: [
                'detail' => $detail,
            ],
        ));
    }
}
