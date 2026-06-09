<?php

namespace Tests\Feature\CancellationV2;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\BookingInsurance;
use App\Models\CancellationAudit;
use App\Models\InsurancePlan;
use App\Models\User;
use App\Services\CancellationV2\CancellationIntegrationsRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CancellationIntegrationsRunnerCoverageBatch18Test extends TestCase
{
    use RefreshDatabase;

    protected function makeBooking(User $client, ?string $paymentIntentId = null): Booking
    {
        $scheduledAt = now()->addDays(4);

        return Booking::create([
            'client_id' => $client->id,
            'date' => $scheduledAt,
            'heure' => $scheduledAt->format('H:i'),
            'scheduled_at' => $scheduledAt,
            'status' => 'annule',
            'devis_estime' => 120.0,
            'stripe_payment_intent_id' => $paymentIntentId,
        ]);
    }

    protected function makeCancellation(Booking $booking, User $client, string $refundMethod = 'stripe'): BookingCancellationV2
    {
        return BookingCancellationV2::create([
            'booking_id' => $booking->id,
            'cancelled_by_user_id' => $client->id,
            'actor_role' => 'client',
            'reason_code' => 'changed_mind',
            'fee_percent_applied' => 0,
            'fee_amount_cents' => 0,
            'refund_amount_cents' => 7500,
            'currency' => 'EUR',
            'refund_method' => $refundMethod,
            'cancelled_at' => now(),
        ]);
    }

    public function test_stripe_refund_returns_manual_when_secret_is_missing(): void
    {
        Config::set('cancellation_v2.integrations.stripe_refund', true);
        Config::set('cancellation_v2.integrations.loyalty_forfeit', false);
        Config::set('cancellation_v2.integrations.promo_restore', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);
        Config::set('services.stripe.secret', '');

        $client = User::factory()->client()->create();
        // Booking HAS a payment intent so the runner passes the no_payment_intent
        // guard and reaches the empty-secret guard.
        $booking = $this->makeBooking($client, 'pi_batch18_secret_missing');
        $row = $this->makeCancellation($booking, $client);

        $result = app(CancellationIntegrationsRunner::class)->run($row);
        $log = (array) $result->integrations_log;

        $this->assertSame('manual', $log['stripe_refund']['status']);
        $this->assertSame('stripe_secret_missing', $log['stripe_refund']['error']);

        // A non-throwing result still writes a "refunded" audit row.
        $this->assertDatabaseHas('cancellation_audits', [
            'cancellation_id' => $row->id,
            'action' => CancellationAudit::ACTION_REFUNDED,
        ]);
    }

    public function test_insurance_cancel_processes_proposed_policies(): void
    {
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.loyalty_forfeit', false);
        Config::set('cancellation_v2.integrations.promo_restore', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', true);

        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);
        $row = $this->makeCancellation($booking, $client, 'mock');

        $plan = InsurancePlan::create([
            'code' => 'cov-batch18',
            'name' => 'Coverage Batch 18',
            'coverage_amount_cents' => 200000,
            'currency' => 'EUR',
        ]);

        // PROPOSED status exercises the second value of the whereIn filter.
        $proposed = BookingInsurance::create([
            'booking_id' => $booking->id,
            'plan_id' => $plan->id,
            'user_id' => $client->id,
            'premium_cents' => 700,
            'coverage_amount_cents' => 200000,
            'currency' => 'EUR',
            'status' => BookingInsurance::STATUS_PROPOSED,
            'external_provider' => 'mock',
        ]);

        // A non-matching status must be ignored by the filter.
        $cancelledAlready = BookingInsurance::create([
            'booking_id' => $booking->id,
            'plan_id' => $plan->id,
            'user_id' => $client->id,
            'premium_cents' => 700,
            'coverage_amount_cents' => 200000,
            'currency' => 'EUR',
            'status' => BookingInsurance::STATUS_CANCELLED,
            'external_provider' => 'mock',
        ]);

        $result = app(CancellationIntegrationsRunner::class)->run($row);
        $log = (array) $result->integrations_log;

        $this->assertSame(1, $log['insurance_cancel']['cancelled_count']);
        $this->assertSame(BookingInsurance::STATUS_CANCELLED, $proposed->fresh()->status);
        $this->assertSame(BookingInsurance::STATUS_CANCELLED, $cancelledAlready->fresh()->status);

        // No stripe branch ran, so no audit rows were written.
        $this->assertSame(0, CancellationAudit::count());
    }
}
