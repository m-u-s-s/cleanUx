<?php

namespace Tests\Unit\Alerts;

use App\Events\BusinessAlertRaised;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Support\Alerts\BusinessAlerts;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessAlertsTest extends TestCase
{
    #[Test]
    public function payment_capture_failed_dispatches_one_alert_with_correct_key(): void
    {
        Event::fake([BusinessAlertRaised::class]);

        $booking = new Booking;
        $booking->id = 42;

        BusinessAlerts::paymentCaptureFailed($booking);

        Event::assertDispatched(BusinessAlertRaised::class, 1);
        Event::assertDispatched(
            BusinessAlertRaised::class,
            fn (BusinessAlertRaised $e) => $e->key === 'payment_capture_failed'
                && isset($e->context['booking_id'])
                && $e->context['booking_id'] === 42
        );
    }

    #[Test]
    public function payout_failed_dispatches_one_alert_with_correct_key_and_context(): void
    {
        Event::fake([BusinessAlertRaised::class]);

        $payout = new ProviderPayout;
        $payout->id = 7;
        $payout->amount = '150.00';
        $payout->currency = 'EUR';

        BusinessAlerts::payoutFailed($payout);

        Event::assertDispatched(BusinessAlertRaised::class, 1);
        Event::assertDispatched(
            BusinessAlertRaised::class,
            fn (BusinessAlertRaised $e) => $e->key === 'payout_failed'
                && isset($e->context['payout_id'])
                && $e->context['payout_id'] === 7
                && isset($e->context['amount'])
        );
    }

    #[Test]
    public function webhook_backlog_dispatches_one_alert_with_count_in_context(): void
    {
        Event::fake([BusinessAlertRaised::class]);

        BusinessAlerts::webhookBacklog(250);

        Event::assertDispatched(BusinessAlertRaised::class, 1);
        Event::assertDispatched(
            BusinessAlertRaised::class,
            fn (BusinessAlertRaised $e) => $e->key === 'webhook_backlog'
                && isset($e->context['count'])
                && $e->context['count'] === 250
        );
    }

    #[Test]
    public function stuck_mission_holding_funds_dispatches_one_alert(): void
    {
        Event::fake([BusinessAlertRaised::class]);

        $mission = new Mission;
        $mission->id = 99;
        $mission->status = 'in_progress';

        BusinessAlerts::stuckMissionHoldingFunds($mission);

        Event::assertDispatched(BusinessAlertRaised::class, 1);
        Event::assertDispatched(
            BusinessAlertRaised::class,
            fn (BusinessAlertRaised $e) => $e->key === 'stuck_mission_holding_funds'
                && isset($e->context['mission_id'])
                && $e->context['mission_id'] === 99
        );
    }

    #[Test]
    public function reconciliation_divergence_dispatches_one_alert_with_detail(): void
    {
        Event::fake([BusinessAlertRaised::class]);

        $detail = [
            'stripe_total' => 10000,
            'db_total' => 9950,
            'delta' => 50,
            'period' => '2026-05',
        ];

        BusinessAlerts::reconciliationDivergence($detail);

        Event::assertDispatched(BusinessAlertRaised::class, 1);
        Event::assertDispatched(
            BusinessAlertRaised::class,
            fn (BusinessAlertRaised $e) => $e->key === 'reconciliation_divergence'
                && isset($e->context['detail'])
        );
    }

    #[Test]
    public function each_emitter_sets_critical_level(): void
    {
        Event::fake([BusinessAlertRaised::class]);

        $booking = new Booking;
        $booking->id = 1;

        $payout = new ProviderPayout;
        $payout->id = 1;
        $payout->amount = '0.00';

        $mission = new Mission;
        $mission->id = 1;
        $mission->status = 'stuck';

        BusinessAlerts::paymentCaptureFailed($booking);
        BusinessAlerts::payoutFailed($payout);
        BusinessAlerts::webhookBacklog(100);
        BusinessAlerts::stuckMissionHoldingFunds($mission);
        BusinessAlerts::reconciliationDivergence(['delta' => 1]);

        // All 5 emitters each fire exactly one event
        Event::assertDispatched(BusinessAlertRaised::class, 5);

        // Every dispatched event must carry level=critical — not just any one of them.
        $dispatched = Event::dispatched(BusinessAlertRaised::class);
        $this->assertCount(5, $dispatched, 'Expected exactly 5 dispatched BusinessAlertRaised events');
        foreach ($dispatched as [$event]) {
            $this->assertSame(
                'critical',
                $event->level,
                "Alert [{$event->key}] must have level=critical but got [{$event->level}]",
            );
        }
    }
}
