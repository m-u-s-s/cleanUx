<?php

namespace Tests\Feature\Spine;

use App\Models\BookingCancellationV2;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyTier;
use App\Models\ProviderWalletTransaction;
use App\Services\CancellationV2\CancellationEngine;
use App\Services\Payments\ProviderWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/** F9 — CancellationV2 refund: quote correctness, Stripe refund, clawback, idempotency. */
class CancellationRefundTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        // Both cashier.secret (read by StripeConnectPaymentService constructor)
        // and services.stripe.secret (read by CancellationIntegrationsRunner)
        // must be set so neither service returns 'manual' / skips the Stripe call.
        config([
            'cashier.secret' => 'sk_test_fake',
            'services.stripe.secret' => 'sk_test_fake',
        ]);
        Stripe::setApiKey('sk_test_fake');

        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);

        // Enable the stripe_refund integration flag
        config(['cancellation_v2.integrations.stripe_refund' => true]);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Seed a CancellationPolicy with one tier that applies a fee_percent. */
    private function seedPolicy(float $feePercent = 20.0): CancellationPolicy
    {
        $policy = CancellationPolicy::create([
            'code' => 'test_policy_f9',
            'name' => 'Test F9 Policy',
            'actor_role' => 'client',
            'trade_codes' => null,  // catch-all
            'is_active' => true,
            'version' => 1,
            'valid_from' => null,
            'valid_until' => null,
        ]);

        CancellationPolicyTier::create([
            'policy_id' => $policy->id,
            'position' => 1,
            'min_hours_before' => 0,
            'max_hours_before' => null,  // open-ended: always matches
            'fee_percent' => $feePercent,
            'fee_flat_cents' => 0,
            'description' => "Fee {$feePercent}%",
        ]);

        $policy->load('tiers');

        return $policy;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1 — quote() returns correct fee and refund amounts
    // ─────────────────────────────────────────────────────────────────────────

    public function test_quote_returns_correct_fee_and_refund_for_client_cancellation(): void
    {
        // Seed a 20% fee policy (always-on tier, 0..open-ended hours).
        $this->seedPolicy(20.0);

        $s = SpineScenario::make()->withDevis(100.00)->build();

        // Put the booking into a captured state with a known amount.
        $s->booking->forceFill([
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => 'pi_quote_test',
            'payment_amount_cents' => 10000,   // 100.00 €
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'currency' => 'EUR',
        ])->save();

        $engine = app(CancellationEngine::class);
        $quote = $engine->quote($s->booking->id, 'client');

        // With 20% fee on 100 €: fee = 2000 cents, refund = 8000 cents.
        $this->assertSame(10000, $quote->bookingAmountCents);
        $this->assertSame(2000, $quote->feeAmountCents, 'Fee must be 20% of booking amount');
        $this->assertSame(8000, $quote->refundAmountCents, 'Refund must be booking amount minus fee');
        $this->assertEqualsWithDelta(20.0, $quote->feePercent, 0.001, 'feePercent must match tier');
        $this->assertNotNull($quote->policy, 'Policy must be resolved');
        $this->assertNotNull($quote->tier, 'Tier must be matched');
        $this->assertFalse($quote->exemptApplied);
        $this->assertEmpty($quote->warnings);
    }

    public function test_quote_returns_full_refund_when_no_policy_matched(): void
    {
        // No policy seeded → warnings=['no_policy_matched'], fee=0, refund=full.
        $s = SpineScenario::make()->withDevis(100.00)->build();

        $s->booking->forceFill([
            'payment_status' => 'captured',
            'payment_amount_cents' => 10000,
            'currency' => 'EUR',
        ])->save();

        $engine = app(CancellationEngine::class);
        $quote = $engine->quote($s->booking->id, 'client');

        $this->assertSame(0, $quote->feeAmountCents, 'No policy → no fee');
        $this->assertSame(10000, $quote->refundAmountCents, 'No policy → full refund');
        $this->assertContains('no_policy_matched', $quote->warnings);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2 — execute() issues Stripe refund + wallet clawback
    // ─────────────────────────────────────────────────────────────────────────

    public function test_execute_issues_stripe_refund_matching_quote_and_writes_clawback(): void
    {
        // Seed a 20% cancellation fee policy.
        $this->seedPolicy(20.0);

        $s = SpineScenario::make()->withDevis(100.00)->build();
        $piId = 'pi_cancel_exec';

        $s->booking->forceFill([
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => $piId,
            'payment_amount_cents' => 10000,
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'currency' => 'EUR',
        ])->save();

        // Seed the provider earning so the wallet has a non-zero balance to claw back.
        $walletService = app(ProviderWalletService::class);
        $walletService->recordEarning($s->booking->fresh());

        // Quote: 20% fee → refund = 8000 cents.
        $engine = app(CancellationEngine::class);
        $quote = $engine->quote($s->booking->id, 'client');
        $this->assertSame(8000, $quote->refundAmountCents);

        // Stub POST /v1/refunds — CancellationIntegrationsRunner calls Refund::create.
        $this->stripe->stub(
            'POST',
            '/v1/refunds',
            StripeFakeResponses::refund('re_cancel_f9', $piId, $quote->refundAmountCents)
        );

        $row = $engine->execute(
            bookingId: $s->booking->id,
            actor: $s->client,
            actorRole: 'client',
            idempotencyKey: 'cancel_test_f9_exec',
        );

        $this->assertInstanceOf(BookingCancellationV2::class, $row);
        $this->assertSame(8000, $row->refund_amount_cents, 'Row refund_amount_cents must match quote');
        $this->assertSame(2000, $row->fee_amount_cents, 'Row fee_amount_cents must match 20% of 10000');

        // Stripe stub must have been called exactly once.
        $refundCalls = array_filter(
            $this->stripe->calls(),
            fn (string $c) => str_contains($c, 'refunds')
        );
        $this->assertCount(1, $refundCalls, 'Stripe POST /v1/refunds must be called exactly once');

        // integrations_log must record a stripe_refund with status=succeeded.
        $log = $row->integrations_log ?? [];
        $this->assertArrayHasKey('stripe_refund', $log, 'integrations_log must contain stripe_refund entry');
        $this->assertSame('succeeded', $log['stripe_refund']['status'] ?? null, 'Stripe refund must succeed');
        $this->assertSame($quote->refundAmountCents, $log['stripe_refund']['refund_amount_cents'] ?? null, 'Logged refund amount must match quote');

        // F9 BUG ASSERTION: a TYPE_REFUND_CLAWBACK wallet row must exist for the provider.
        $clawback = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)
            ->where('direction', ProviderWalletTransaction::DIRECTION_DEBIT)
            ->first();

        $this->assertNotNull(
            $clawback,
            'F9 BUG: CancellationEngine::execute() triggered a Stripe refund of '
            .$quote->refundAmountCents.' cents but did NOT write a wallet clawback. '
            .'The provider retains money the client was refunded. '
            .'CancellationIntegrationsRunner::tryStripeRefund must call '
            .'ProviderWalletService::recordRefundClawback after a successful Stripe refund.'
        );

        // Clawback amount should be proportional: refund(8000) × provider(8000) / total(10000) = 6400 cents = 64.00 €.
        $expectedClawbackCents = (int) round($quote->refundAmountCents * 8000 / 10000);
        $this->assertEqualsWithDelta(
            round($expectedClawbackCents / 100, 2),
            (float) $clawback->amount,
            0.01,
            'Clawback must be proportional: refundCents × providerCents / totalCents'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3 — Idempotency: execute() twice with the same key returns the same row
    // ─────────────────────────────────────────────────────────────────────────

    public function test_execute_is_idempotent_on_same_key(): void
    {
        $this->seedPolicy(10.0);

        $s = SpineScenario::make()->withDevis(100.00)->build();
        $piId = 'pi_idem_f9';

        $s->booking->forceFill([
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => $piId,
            'payment_amount_cents' => 10000,
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'currency' => 'EUR',
        ])->save();

        // Stub the refund for the first call.
        $this->stripe->stub(
            'POST',
            '/v1/refunds',
            StripeFakeResponses::refund('re_idem_f9', $piId, 9000)
        );

        $key = 'cancel_idem_test_f9';
        $engine = app(CancellationEngine::class);

        $row1 = $engine->execute($s->booking->id, $s->client, 'client', idempotencyKey: $key);
        // Second call — must return the existing row without hitting Stripe again.
        $row2 = $engine->execute($s->booking->id, $s->client, 'client', idempotencyKey: $key);

        $this->assertSame($row1->id, $row2->id, 'Second execute with same idempotency_key must return the same BookingCancellationV2 row');

        // Only one BookingCancellationV2 row must exist.
        $count = BookingCancellationV2::query()
            ->where('booking_id', $s->booking->id)
            ->count();
        $this->assertSame(1, $count, 'execute() must not create duplicate cancellation rows on retry');

        // Stripe must have been called exactly once (on the first execute, not the second).
        $refundCalls = array_filter(
            $this->stripe->calls(),
            fn (string $c) => str_contains($c, 'refunds')
        );
        $this->assertCount(1, $refundCalls, 'Stripe refund must be called exactly once despite two execute() calls');
    }
}
