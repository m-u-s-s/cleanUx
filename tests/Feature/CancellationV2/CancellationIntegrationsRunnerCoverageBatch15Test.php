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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CancellationIntegrationsRunnerCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    protected function makeBooking(User $client): Booking
    {
        $scheduledAt = now()->addDays(3);

        return Booking::create([
            'client_id' => $client->id,
            'date' => $scheduledAt,
            'heure' => $scheduledAt->format('H:i'),
            'scheduled_at' => $scheduledAt,
            'status' => 'annule',
            'devis_estime' => 100.0,
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
            'refund_amount_cents' => 5000,
            'currency' => 'EUR',
            'refund_method' => $refundMethod,
            'cancelled_at' => now(),
        ]);
    }

    public function test_runner_executes_every_enabled_integration_branch(): void
    {
        Config::set('cancellation_v2.integrations.stripe_refund', true);
        Config::set('cancellation_v2.integrations.loyalty_forfeit', true);
        Config::set('cancellation_v2.integrations.promo_restore', true);
        Config::set('cancellation_v2.integrations.insurance_cancel', true);

        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);
        $row = $this->makeCancellation($booking, $client);

        // Active insurance attached to the booking should be auto-cancelled.
        $plan = InsurancePlan::create([
            'code' => 'cov-batch15',
            'name' => 'Coverage Batch 15',
            'coverage_amount_cents' => 100000,
            'currency' => 'EUR',
        ]);
        $insurance = BookingInsurance::create([
            'booking_id' => $booking->id,
            'plan_id' => $plan->id,
            'user_id' => $client->id,
            'premium_cents' => 500,
            'coverage_amount_cents' => 100000,
            'currency' => 'EUR',
            'status' => BookingInsurance::STATUS_ACTIVE,
            'external_provider' => 'mock',
        ]);

        $result = app(CancellationIntegrationsRunner::class)->run($row);
        $log = (array) $result->integrations_log;

        // Stripe branch: booking has no payment intent → soft-fail "manual".
        $this->assertSame('manual', $log['stripe_refund']['status']);
        $this->assertSame('no_payment_intent', $log['stripe_refund']['error']);

        // Loyalty hook fired.
        $this->assertTrue($log['loyalty']['notified']);

        // Promo restore ran (no matching redemption rows → zero reversed).
        $this->assertSame(0, $log['promo_restore']['rows_reversed']);

        // Insurance cancel processed the active policy.
        $this->assertSame(1, $log['insurance_cancel']['cancelled_count']);
        $this->assertSame(BookingInsurance::STATUS_CANCELLED, $insurance->fresh()->status);

        // A "refunded" audit row was written for the stripe attempt.
        $this->assertDatabaseHas('cancellation_audits', [
            'cancellation_id' => $row->id,
            'action' => CancellationAudit::ACTION_REFUNDED,
        ]);
    }

    public function test_runner_promo_restore_soft_fails_on_constraint_violation(): void
    {
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.loyalty_forfeit', false);
        Config::set('cancellation_v2.integrations.promo_restore', true);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);

        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);
        $row = $this->makeCancellation($booking, $client, 'mock');

        $promoId = DB::table('promo_codes')->insertGetId([
            'code' => 'BATCH15',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('promo_code_redemptions')->insert([
            'promo_code_id' => $promoId,
            'user_id' => $client->id,
            'booking_id' => $booking->id,
            'status' => 'applied',
            'discount_amount' => 10,
            'currency' => 'EUR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The runner writes status='reversed', which is outside the column's
        // enum CHECK constraint under SQLite → the promo branch soft-fails and
        // records the error instead of crashing the whole runner.
        $result = app(CancellationIntegrationsRunner::class)->run($row);
        $log = (array) $result->integrations_log;

        $this->assertArrayHasKey('promo_restore_error', $log);
        $this->assertSame('applied', DB::table('promo_code_redemptions')
            ->where('booking_id', $booking->id)->value('status'));
        $this->assertArrayNotHasKey('stripe_refund', $log);
    }

    public function test_runner_skips_stripe_branch_for_non_stripe_refund_method(): void
    {
        Config::set('cancellation_v2.integrations.stripe_refund', true);
        Config::set('cancellation_v2.integrations.loyalty_forfeit', false);
        Config::set('cancellation_v2.integrations.promo_restore', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);

        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);
        $row = $this->makeCancellation($booking, $client, 'mock');

        $result = app(CancellationIntegrationsRunner::class)->run($row);
        $log = (array) $result->integrations_log;

        $this->assertArrayNotHasKey('stripe_refund', $log);
        $this->assertDatabaseMissing('cancellation_audits', [
            'cancellation_id' => $row->id,
        ]);
    }

    public function test_runner_is_a_noop_when_all_integrations_disabled(): void
    {
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.loyalty_forfeit', false);
        Config::set('cancellation_v2.integrations.promo_restore', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);

        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);
        $row = $this->makeCancellation($booking, $client, 'stripe');

        $result = app(CancellationIntegrationsRunner::class)->run($row);

        $this->assertSame([], (array) $result->integrations_log);
        $this->assertSame(0, CancellationAudit::count());
    }
}
