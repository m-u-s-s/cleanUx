<?php

namespace Tests\Feature\Spine;

use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\ProviderProfile;
use App\Models\StripeReconciliationRun;
use App\Models\User;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\StripeReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/**
 * F5 — Payout routes to the ASSIGNED provider only.
 * F8 — Reconciliation DETECTS DB ↔ Stripe divergence rather than silently passing.
 */
class PayoutRoutingTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashier.secret' => 'sk_test_fake', 'services.stripe.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // F5 — Payout routes to the assigned provider only
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Given two providers with distinct Stripe Connect accounts, capturing
     * provider-A's mission must route the ProviderPayout exclusively to A and
     * record A's connect account on the booking. Provider B must never appear.
     */
    public function test_f5_payout_routes_to_assigned_provider_not_to_bystander(): void
    {
        // ── Scenario A (the real booking) ──────────────────────────────────
        $sA = SpineScenario::make()->withDevis(100.00)->build();

        // ── Provider B (a bystander with its own distinct Connect account) ──
        $acctB = 'acct_provider_test_'.uniqid('b_');
        $providerB = User::factory()->employe()->create([
            'stripe_connect_account_id' => $acctB,
            'stripe_connect_status' => 'active',
        ]);
        ProviderProfile::factory()->create([
            'user_id' => $providerB->id,
            'stripe_connect_account_id' => $acctB,
            'stripe_connect_status' => 'active',
        ]);

        // Sanity: the two Connect accounts are genuinely distinct.
        $this->assertNotSame(
            $sA->provider->stripe_connect_account_id,
            $providerB->stripe_connect_account_id,
            'Pre-condition: provider A and provider B must have distinct Connect accounts'
        );

        $piId = 'pi_f5_routing';

        // ── Step 1: authorize booking A ──
        $this->stripe->stub(
            'POST',
            '/v1/payment_intents',
            StripeFakeResponses::paymentIntent($piId, 'requires_capture', [
                'transfer_data' => ['destination' => $sA->provider->stripe_connect_account_id],
            ])
        );
        app(MissionPaymentService::class)->authorize($sA->booking, 'pm_card_visa');
        $sA->booking->refresh();

        $this->assertSame('authorized', $sA->booking->payment_status);
        $this->assertSame($piId, $sA->booking->stripe_payment_intent_id);

        // ── Step 2: capture A's mission ──
        $this->stripe->stub(
            'POST',
            "/v1/payment_intents/{$piId}/capture",
            StripeFakeResponses::paymentIntent($piId, 'succeeded', [
                'transfer_data' => ['destination' => $sA->provider->stripe_connect_account_id],
            ])
        );
        $payout = app(StripeConnectPaymentService::class)->captureMissionPayment($sA->mission->fresh());

        // ── Assertions ─────────────────────────────────────────────────────

        // F5-a: ProviderPayout must be created and belong to provider A.
        $this->assertInstanceOf(ProviderPayout::class, $payout, 'F5: capture must create a ProviderPayout');
        $this->assertSame(
            $sA->provider->id,
            $payout->provider_user_id,
            'F5: ProviderPayout.provider_user_id must equal provider A id'
        );

        // F5-b: ProviderPayout must NOT belong to provider B.
        $this->assertNotSame(
            $providerB->id,
            $payout->provider_user_id,
            'F5 BUG: ProviderPayout.provider_user_id must never point at provider B'
        );

        // F5-c: payout metadata must reference the correct booking and PI.
        // The PI was created with transfer_data.destination = provider A's connect account
        // (MissionPaymentService::authorize uses booking.employe → employe.stripe_connect_account_id).
        // The ProviderPayout metadata records the booking_id and stripe_payment_intent_id so
        // we can verify the payout is linked to the correct booking (not provider B's).
        $payoutMeta = $payout->metadata ?? [];
        $this->assertSame(
            $sA->booking->id,
            $payoutMeta['booking_id'] ?? null,
            'F5: ProviderPayout metadata.booking_id must reference booking A'
        );

        // F5-d: the payout PI id must be A's PI, never any PI belonging to provider B's scenario.
        $this->assertSame(
            $piId,
            $payoutMeta['stripe_payment_intent_id'] ?? null,
            'F5: ProviderPayout metadata.stripe_payment_intent_id must be the PI authorized for booking A'
        );

        // F5-e (additional): verify the PI create call sent provider A's connect account
        // as transfer_data.destination and NOT provider B's account.
        // The fake client records the PI create stub (POST /v1/payment_intents) but not
        // the outgoing params; we verify routing indirectly by asserting the employe_id
        // on the booking matches provider A, which is what MissionPaymentService reads to
        // determine the destination.
        $sA->booking->refresh();
        $this->assertSame(
            $sA->provider->id,
            $sA->booking->employe_id,
            'F5: booking.employe_id must be provider A — this is the column authorize() uses for transfer_data.destination'
        );
        $this->assertNotSame(
            $providerB->id,
            $sA->booking->employe_id,
            'F5 BUG: booking.employe_id must never reference provider B'
        );

        // F5-e: no ProviderPayout must exist for provider B from this mission.
        $payoutsForB = ProviderPayout::where('provider_user_id', $providerB->id)->count();
        $this->assertSame(
            0,
            $payoutsForB,
            'F5 BUG: zero ProviderPayout rows must exist for the bystander provider B'
        );

        // F5-f: exactly one ProviderPayout must exist for provider A.
        $payoutsForA = ProviderPayout::where('provider_user_id', $sA->provider->id)->count();
        $this->assertSame(
            1,
            $payoutsForA,
            'F5: exactly one ProviderPayout must exist for provider A'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // F8 — Reconciliation detects divergence
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Divergence tested: DB booking says "captured" but the Stripe PI reports
     * "requires_capture" (status mismatch type = payment_status_mismatch, severity error).
     *
     * This is divergence (a) from the task spec — the one StripeReconciliationService
     * ::reconcilePaymentIntents() actually inspects via mapPiStatusToBookingStatus():
     *   requires_capture → 'authorized'  (expected by Stripe)
     *   booking.payment_status = 'captured' → mismatch
     *
     * The service calls PaymentIntent::all({created:…}) which maps to
     * GET /v1/payment_intents with query params. The fake client matches on the path
     * without query strings (GET /v1/payment_intents). We stub that path and return a
     * Stripe list object containing our deliberate-mismatch PI.
     */
    public function test_f8_reconciliation_detects_status_mismatch_between_stripe_and_db(): void
    {
        $piId = 'pi_f8_mismatch';

        // ── Seed a booking that is "captured" in DB ──
        $s = SpineScenario::make()->withDevis(100.00)->build();
        $s->booking->forceFill([
            'stripe_payment_intent_id' => $piId,
            'payment_status' => 'captured',   // DB says captured
            'payment_captured_at' => now(),
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'payment_amount_cents' => 10000,
        ])->save();

        // ── Stub Stripe to return the PI as still "requires_capture" (DB ahead) ──
        // reconcilePaymentIntents() calls PaymentIntent::all(…)
        //   → GET /v1/payment_intents
        // The SDK then iterates $intents->data.
        // A Stripe list object has shape: {object:'list', data:[…], has_more:false, url:'…'}
        $this->stripe->stub(
            'GET',
            '/v1/payment_intents',
            [
                'object' => 'list',
                'data' => [
                    StripeFakeResponses::paymentIntent($piId, 'requires_capture'),
                ],
                'has_more' => false,
                'url' => '/v1/payment_intents',
            ]
        );

        $svc = new StripeReconciliationService;
        // Scope to payment_intents only; use a wide window so our booking is in range.
        $run = $svc->run(
            StripeReconciliationRun::SCOPE_PAYMENT_INTENTS,
            Carbon::now()->subDays(1)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        // ── Assert the run completed (not failed) ──
        $this->assertSame(
            StripeReconciliationRun::STATUS_COMPLETED,
            $run->status,
            'F8: reconciliation run must complete without exception'
        );

        // ── Assert at least one mismatch was detected ──
        $mismatches = (array) $run->mismatches;
        $this->assertGreaterThan(
            0,
            count($mismatches),
            'F8: reconciliation must report at least one mismatch for the seeded divergence'
        );

        // ── Assert the specific mismatch type and severity ──
        $statusMismatches = array_filter(
            $mismatches,
            fn ($m) => ($m['type'] ?? '') === 'payment_status_mismatch'
        );

        $this->assertNotEmpty(
            $statusMismatches,
            'F8 BUG: reconciliation must flag a payment_status_mismatch when '
            .'booking.payment_status=captured but Stripe PI status=requires_capture (DB ahead of Stripe)'
        );

        $mismatch = reset($statusMismatches);
        $this->assertSame(
            'error',
            $mismatch['severity'],
            'F8: a payment_status_mismatch divergence must have severity=error'
        );

        // ── Assert requires_attention counter is non-zero ──
        $this->assertGreaterThan(
            0,
            $run->requires_attention,
            'F8: run.requires_attention must be > 0 when an error-severity mismatch is found'
        );

        // ── Assert the mismatch references the correct PI ──
        $this->assertSame(
            $piId,
            $mismatch['stripe_id'],
            'F8: mismatch must reference the exact Stripe PI id that triggered the divergence'
        );
    }

    /**
     * Clean case: a booking that is "captured" in DB and Stripe also reports
     * "succeeded" → reconciliation must report ZERO payment_status_mismatch
     * entries for this PI.
     */
    public function test_f8_reconciliation_reports_no_mismatch_when_db_and_stripe_agree(): void
    {
        $piId = 'pi_f8_clean';

        $s = SpineScenario::make()->withDevis(100.00)->build();
        $s->booking->forceFill([
            'stripe_payment_intent_id' => $piId,
            'payment_status' => 'captured',   // DB = captured
            'payment_captured_at' => now(),
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'payment_amount_cents' => 10000,
        ])->save();

        // Stripe also reports "succeeded" → mapPiStatusToBookingStatus('succeeded') = 'captured' ✓
        $this->stripe->stub(
            'GET',
            '/v1/payment_intents',
            [
                'object' => 'list',
                'data' => [
                    StripeFakeResponses::paymentIntent($piId, 'succeeded'),
                ],
                'has_more' => false,
                'url' => '/v1/payment_intents',
            ]
        );

        $svc = new StripeReconciliationService;
        $run = $svc->run(
            StripeReconciliationRun::SCOPE_PAYMENT_INTENTS,
            Carbon::now()->subDays(1)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);

        $statusMismatches = array_filter(
            (array) $run->mismatches,
            fn ($m) => ($m['type'] ?? '') === 'payment_status_mismatch'
        );

        $this->assertEmpty(
            $statusMismatches,
            'F8 (clean case): when DB and Stripe agree (both captured/succeeded), '
            .'reconciliation must report zero payment_status_mismatch entries'
        );
    }

    /**
     * F8 gap (finding 4c) — A booking marked "captured" with NO ProviderPayout row
     * is a real launch-blocking gap: the provider's money ledger entry is missing.
     *
     * StripeReconciliationService::reconcilePaymentIntents() does NOT currently flag
     * this case because it only checks PI.status ↔ booking.payment_status; it never
     * cross-references the payout table.
     *
     * A minimal check has been added to reconcilePaymentIntents() that, after the
     * status-map comparison, also verifies a ProviderPayout exists for every
     * "captured" booking found via a Stripe PI.
     *
     * This test acts as a regression guard for that addition.
     */
    public function test_f8_reconciliation_flags_captured_booking_with_missing_payout_row(): void
    {
        $piId = 'pi_f8_nopayout';

        $s = SpineScenario::make()->withDevis(100.00)->build();
        $s->booking->forceFill([
            'stripe_payment_intent_id' => $piId,
            'payment_status' => 'captured',
            'payment_captured_at' => now(),
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'payment_amount_cents' => 10000,
        ])->save();

        // Deliberately leave NO ProviderPayout row for this booking.
        $this->assertSame(0, ProviderPayout::count(), 'Pre-condition: no ProviderPayout row must exist');

        // Stripe agrees: PI is "succeeded" (so no status-mismatch — only the missing payout gap fires).
        $this->stripe->stub(
            'GET',
            '/v1/payment_intents',
            [
                'object' => 'list',
                'data' => [
                    StripeFakeResponses::paymentIntent($piId, 'succeeded'),
                ],
                'has_more' => false,
                'url' => '/v1/payment_intents',
            ]
        );

        $svc = new StripeReconciliationService;
        $run = $svc->run(
            StripeReconciliationRun::SCOPE_PAYMENT_INTENTS,
            Carbon::now()->subDays(1)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);

        $missingPayoutMismatches = array_filter(
            (array) $run->mismatches,
            fn ($m) => ($m['type'] ?? '') === 'captured_booking_missing_payout'
        );

        $this->assertNotEmpty(
            $missingPayoutMismatches,
            'F8 gap (finding 4c): reconciliation must flag a "captured_booking_missing_payout" '
            .'mismatch when a captured booking has no ProviderPayout row. '
            .'Without this check, a missing ledger entry (money not tracked) silently passes reconciliation.'
        );

        $mismatch = reset($missingPayoutMismatches);
        $this->assertSame('error', $mismatch['severity'], 'Missing payout must be severity=error');
        $this->assertSame($piId, $mismatch['stripe_id']);
    }
}
