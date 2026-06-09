<?php

namespace Tests\Feature\Cancellation;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Cancellation\CancelBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Supplementary coverage for App\Services\Cancellation\CancelBookingService.
 *
 * The sibling CancelBookingServiceTest already covers the happy paths through
 * the early-return guards. This batch drives the deeper helper branches:
 *   - tryRefundPartial: payment_status not eligible, refund amount <= 0, and the
 *     full path that reaches StripeConnectPaymentService (caught soft-fail).
 *   - tryCaptureFull: authorized no-show that reaches MissionPaymentService.
 *   - applyProviderPenalty: crossing the 30-day cancellation threshold warning.
 *
 * The external Payment services are reached inside their try/catch blocks, so
 * any Stripe/DB failure is swallowed and the test remains deterministic.
 */
class CancelBookingServiceCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_client_cancellation_with_ineligible_payment_status_skips_refund(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00',
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
            'stripe_payment_intent_id' => 'pi_test_pending',
            'payment_status' => 'pending', // not authorized/captured → early return
        ]);

        $result = app(CancelBookingService::class)->cancelByClient($booking, $client);

        $this->assertTrue($result['ok']);
        $this->assertSame('annule', $booking->fresh()->status);
    }

    public function test_client_cancellation_with_full_fee_skips_zero_refund(): void
    {
        // Cancel <30 min before start → 100% fee. Refund amount = price - fee = 0.
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '10:20:00', // 20 min before → 100% tier
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
            'stripe_payment_intent_id' => 'pi_test_fullfee',
            'payment_status' => 'authorized',
        ]);

        $result = app(CancelBookingService::class)->cancelByClient($booking, $client);

        $this->assertTrue($result['ok']);
        $this->assertSame(100.0, $result['fee_details']['fee_amount']);
        $this->assertSame('annule', $booking->fresh()->status);
    }

    public function test_client_cancellation_with_partial_refund_reaches_payment_service(): void
    {
        // 1h before → 50% fee, price 100 → refundAmount 50 > 0, drives the
        // StripeConnectPaymentService call (any failure is caught & logged).
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $client = User::factory()->create();
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00',
            'estimated_price' => 100,
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'created_at' => now()->subHours(48),
            'stripe_payment_intent_id' => 'pi_test_partial',
            'payment_status' => 'captured',
        ]);

        $result = app(CancelBookingService::class)->cancelByClient($booking, $client, 'partial refund path');

        $this->assertTrue($result['ok']);
        $this->assertSame(50.0, $result['fee_details']['fee_amount']);
        $this->assertSame('annule', $booking->fresh()->status);
    }

    public function test_no_show_with_authorized_payment_reaches_capture(): void
    {
        // payment_status authorized → tryCaptureFull reaches MissionPaymentService.
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 30, 0));

        $provider = User::factory()->create();
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '10:00:00', // 30 min ago, past 15 min grace
            'estimated_price' => 80,
            'payment_status' => 'authorized',
            'stripe_payment_intent_id' => 'pi_test_capture',
        ]);

        $result = app(CancelBookingService::class)->markClientNoShow($booking, $provider);

        $this->assertTrue($result['ok']);
        $this->assertSame('client_no_show', $result['type']);
        $this->assertSame(80.0, $result['fee_amount']);
        $this->assertSame('annule', $booking->fresh()->status);
    }

    public function test_provider_penalty_crossing_threshold_logs_warning(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));

        $provider = User::factory()->create();
        // Already at 4 cancellations; the late one pushes count to the
        // max_cancellations_per_30d threshold (5) → warning branch.
        $profile = ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'metadata' => ['reliability_penalty_total' => 30, 'cancellations_30d_count' => 4],
        ]);

        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '10:10:00', // 10 min before → late, penalty applies
        ]);

        Log::spy();

        $result = app(CancelBookingService::class)->cancelByProvider($booking, $provider, 'late drop');

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['penalty']['is_free']);

        $meta = $profile->fresh()->metadata;
        $this->assertSame(5, $meta['cancellations_30d_count']);
        $this->assertArrayHasKey('last_cancellation_at', $meta);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains((string) $message, 'cancellation threshold'))
            ->atLeast()
            ->once();
    }

    protected function makeBooking(array $overrides = []): Booking
    {
        $client = User::factory()->create();

        return Booking::create(array_merge([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'estimated_price' => 100,
        ], $overrides));
    }
}
