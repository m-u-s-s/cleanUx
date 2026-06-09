<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Models\StripeReconciliationRun;
use App\Models\User;
use App\Services\Payments\StripeReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/**
 * Coverage for App\Services\Payments\StripeReconciliationService.
 *
 * Drives the payment-intent and payout reconciliation loops through the
 * project's FakeStripeHttpClient so no network is hit, exercising every
 * mismatch branch plus the Stripe-unavailable and API-error guards.
 */
class StripeReconciliationServiceCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $fakeStripe;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cashier.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');
        $this->fakeStripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->fakeStripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    private function service(): StripeReconciliationService
    {
        $service = app(StripeReconciliationService::class);
        Stripe::setApiKey('sk_test_fake');

        return $service;
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    private function stubPaymentIntentList(array $data): void
    {
        $this->fakeStripe->stub('GET', '/v1/payment_intents', [
            'object' => 'list',
            'url' => '/v1/payment_intents',
            'has_more' => false,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     */
    private function stubPayoutList(array $data): void
    {
        $this->fakeStripe->stub('GET', '/v1/payouts', [
            'object' => 'list',
            'url' => '/v1/payouts',
            'has_more' => false,
            'data' => $data,
        ]);
    }

    private function bookingWith(string $paymentIntentId, string $paymentStatus): Booking
    {
        return Booking::factory()->create([
            'stripe_payment_intent_id' => $paymentIntentId,
            'payment_status' => $paymentStatus,
        ]);
    }

    private function payoutBody(string $id, string $status, int $amount = 5000): array
    {
        return [
            'id' => $id,
            'object' => 'payout',
            'status' => $status,
            'amount' => $amount,
            'currency' => 'eur',
            'created' => 1700000000,
        ];
    }

    // ──────────────────────────────────────────────
    // payment_intents scope — every PI mismatch branch
    // ──────────────────────────────────────────────

    public function test_payment_intent_scope_reports_all_mismatch_branches(): void
    {
        // 1. Orphan PI absent from DB → payment_intent_not_in_db (error)
        // 2. PI matching a booking whose status differs → payment_status_mismatch
        $mismatchBooking = $this->bookingWith('pi_status_drift', 'pending');

        // 3. Captured booking + mission with assigned provider but no payout
        $provider = User::factory()->employe()->create();
        $missingPayoutBooking = $this->bookingWith('pi_missing_payout', 'captured');
        Mission::factory()->create([
            'rendez_vous_id' => $missingPayoutBooking->id,
            'lead_provider_user_id' => $provider->id,
        ]);

        // 4. Captured booking + mission + matching ProviderPayout → clean
        $okBooking = $this->bookingWith('pi_with_payout', 'captured');
        Mission::factory()->create([
            'rendez_vous_id' => $okBooking->id,
            'lead_provider_user_id' => $provider->id,
        ]);
        ProviderPayout::factory()->create([
            'provider_user_id' => $provider->id,
            'metadata' => ['stripe_payment_intent_id' => 'pi_with_payout'],
        ]);

        // 5. Captured booking with NO mission → no payout expectation → clean
        $noMissionBooking = $this->bookingWith('pi_no_mission', 'captured');

        $this->stubPaymentIntentList([
            StripeFakeResponses::paymentIntent('pi_orphan_db', 'succeeded'),
            StripeFakeResponses::paymentIntent('pi_status_drift', 'succeeded'),
            StripeFakeResponses::paymentIntent('pi_missing_payout', 'succeeded'),
            StripeFakeResponses::paymentIntent('pi_with_payout', 'succeeded'),
            StripeFakeResponses::paymentIntent('pi_no_mission', 'succeeded'),
        ]);

        $run = $this->service()->run(StripeReconciliationRun::SCOPE_PAYMENT_INTENTS);

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(5, $run->items_checked);

        $types = collect($run->mismatches)->pluck('type')->all();
        $this->assertContains('payment_intent_not_in_db', $types);
        $this->assertContains('payment_status_mismatch', $types);
        $this->assertContains('captured_booking_missing_payout', $types);
        $this->assertNotContains('stripe_unavailable', $types);

        // Clean bookings produced no extra rows.
        $this->assertCount(3, $run->mismatches);
        $this->assertSame(3, $run->requires_attention);

        // booking ids unused beyond seeding — touch them to silence analysers.
        $this->assertNotNull($mismatchBooking->id);
        $this->assertNotNull($okBooking->id);
        $this->assertNotNull($noMissionBooking->id);
    }

    public function test_payment_intent_with_unmapped_status_is_not_flagged(): void
    {
        // 'requires_capture' maps to 'authorized'; align the booking so no drift.
        $this->bookingWith('pi_authorized', 'authorized');

        $this->stubPaymentIntentList([
            StripeFakeResponses::paymentIntent('pi_authorized', 'requires_capture'),
        ]);

        $run = $this->service()->run(StripeReconciliationRun::SCOPE_PAYMENT_INTENTS);

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);
        $this->assertSame([], (array) $run->mismatches);
        $this->assertSame(1, $run->items_checked);
    }

    // ──────────────────────────────────────────────
    // payouts scope — every payout mismatch branch
    // ──────────────────────────────────────────────

    public function test_payout_scope_reports_orphan_and_drift_and_clean(): void
    {
        $provider = User::factory()->employe()->create();

        // Drift: local row exists but not yet PAID while Stripe says paid.
        ProviderPayout::factory()->create([
            'provider_user_id' => $provider->id,
            'provider_payout_id' => 'po_drift',
            'status' => ProviderPayout::STATUS_PENDING,
        ]);

        // Clean: local row already PAID.
        ProviderPayout::factory()->create([
            'provider_user_id' => $provider->id,
            'provider_payout_id' => 'po_clean',
            'status' => ProviderPayout::STATUS_PAID,
        ]);

        $this->stubPayoutList([
            $this->payoutBody('po_orphan', 'paid'),     // no local entry → orphan warning
            $this->payoutBody('po_drift', 'paid'),      // local pending → drift error
            $this->payoutBody('po_clean', 'paid'),      // local paid → clean
            $this->payoutBody('po_in_transit', 'in_transit'), // not paid, no local → ignored
        ]);

        $run = $this->service()->run(StripeReconciliationRun::SCOPE_PAYOUTS);

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(4, $run->items_checked);

        $types = collect($run->mismatches)->pluck('type')->all();
        $this->assertContains('stripe_payout_orphan', $types);
        $this->assertContains('payout_status_drift', $types);
        $this->assertCount(2, $run->mismatches);

        // Orphan is a warning, drift is an error.
        $this->assertSame(1, $run->requires_attention);
    }

    // ──────────────────────────────────────────────
    // Stripe API error is captured as a mismatch, not a thrown run failure
    // ──────────────────────────────────────────────

    public function test_stripe_api_error_is_recorded_as_mismatch(): void
    {
        $this->fakeStripe->stub('GET', '/v1/payment_intents', [
            'error' => ['message' => 'rate limited', 'type' => 'api_error'],
        ], 429);

        $run = $this->service()->run(StripeReconciliationRun::SCOPE_PAYMENT_INTENTS);

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);
        $types = collect($run->mismatches)->pluck('type')->all();
        $this->assertContains('stripe_api_error', $types);
    }

    // ──────────────────────────────────────────────
    // Stripe unavailable guard for both scopes (ALL scope)
    // ──────────────────────────────────────────────

    public function test_all_scope_flags_unavailable_when_no_credentials(): void
    {
        config(['cashier.secret' => null]);
        putenv('STRIPE_SECRET');

        $run = $this->service()->run(StripeReconciliationRun::SCOPE_ALL);

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(0, $run->items_checked);

        $types = collect($run->mismatches)->pluck('type')->all();
        $this->assertSame(['stripe_unavailable', 'stripe_unavailable'], $types);
        $scopes = collect($run->mismatches)->pluck('scope')->all();
        $this->assertSame(['payment_intents', 'payouts'], $scopes);
    }

    // ──────────────────────────────────────────────
    // Default period + triggered-by user persisted
    // ──────────────────────────────────────────────

    public function test_default_period_and_triggered_by_user_are_persisted(): void
    {
        $this->stubPaymentIntentList([]);
        $this->stubPayoutList([]);

        $admin = User::factory()->admin()->create();
        $run = $this->service()->run(StripeReconciliationRun::SCOPE_ALL, null, null, $admin);

        $this->assertSame($admin->id, $run->triggered_by_user_id);
        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);
        $this->assertNotNull($run->period_start);
        $this->assertNotNull($run->period_end);
        $this->assertSame([], (array) $run->mismatches);
    }
}
