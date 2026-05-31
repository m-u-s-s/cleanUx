<?php

namespace Tests\Feature\Spine;

use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Services\Payments\ProviderWalletService;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/**
 * F3 — Refund after capture must write a wallet clawback (debit).
 *
 * After a full refund to the client the provider's net available balance
 * must return to ~0. Without a clawback the provider keeps money that
 * was returned to the client — a real money-out disaster.
 */
class RefundClawbackTest extends TestCase
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

    public function test_full_refund_writes_clawback_and_zeroes_provider_balance(): void
    {
        // ── 1. Build scenario: captured booking with 80 € provider earning ──
        $s = SpineScenario::make()->withDevis(100.00)->build();

        $piId = 'pi_f3';

        // Force the booking into the captured state with known financials.
        $s->booking->forceFill([
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => $piId,
            'provider_amount_cents' => 8000,   // 80.00 €
            'platform_fee_cents' => 2000,       // 20.00 €
            'payment_amount_cents' => 10000,    // 100.00 € total
            'currency' => 'EUR',
        ])->save();

        // Seed the wallet earning so the provider has 80 € available before refund.
        $walletService = app(ProviderWalletService::class);
        $earning = $walletService->recordEarning($s->booking->fresh());
        $this->assertNotNull($earning, 'Pre-condition: earning must be seeded');

        $balanceBefore = $walletService->balance($s->provider->id);
        // recordEarning credits 80 € (earning) and debits 20 € (platform_fee) → net 60 € available.
        $this->assertEqualsWithDelta(60.00, $balanceBefore['available'], 0.01, 'Provider should have 60 € net available before refund (80 € earning − 20 € platform fee)');

        // ── 2. Stub the Stripe refund endpoint ──
        // refundMissionPayment calls Refund::create which posts to /v1/refunds.
        // It does NOT retrieve the PI first, so only the refund stub is needed.
        $this->stripe->stub(
            'POST',
            '/v1/refunds',
            StripeFakeResponses::refund('re_f3', $piId, 10000)
        );

        // ── 3. Call refundMissionPayment (full refund — amountCents = null) ──
        $stripeRefund = app(StripeConnectPaymentService::class)
            ->refundMissionPayment($s->booking->fresh());

        $this->assertNotNull($stripeRefund, 'Stripe refund object must be returned');
        $this->assertSame('re_f3', $stripeRefund->id);

        // Booking payment_status must be 'refunded'.
        $s->booking->refresh();
        $this->assertSame('refunded', $s->booking->payment_status, 'Booking payment_status must be "refunded" after full refund');

        // ── 4. Assert clawback was written ──
        $clawback = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)
            ->where('direction', ProviderWalletTransaction::DIRECTION_DEBIT)
            ->first();

        $this->assertNotNull(
            $clawback,
            'F3 BUG: a refund_clawback (DEBIT) wallet transaction must be written when a captured payment is refunded — provider must not retain money that was returned to the client'
        );

        $this->assertEqualsWithDelta(
            80.00,
            (float) $clawback->amount,
            0.01,
            'Clawback amount must equal the provider earning amount (80 €)'
        );

        $this->assertSame(ProviderWalletTransaction::STATUS_AVAILABLE, $clawback->status);

        // ── 5. Net balance must be ~0 after earning + clawback ──
        $balanceAfter = $walletService->balance($s->provider->id);

        $this->assertEqualsWithDelta(
            0.00,
            $balanceAfter['available'],
            0.01,
            'F3: after a full refund the provider available balance must be 0. '
            .'Ledger: +80 € earning − 20 € platform_fee − 80 € clawback = −20 → clamped to 0. '
            .'A non-zero positive balance means the provider keeps money the client was refunded.'
        );
    }

    public function test_partial_refund_claws_back_proportional_share(): void
    {
        // Scenario: €100 booking, €80 provider share, €20 platform fee.
        // Client gets a €50 partial refund.
        // CORRECT clawback = 50 × (80/100) = €40.
        // BUG before fix: clawback used the raw €50, over-charging the provider.
        $s = SpineScenario::make()->withDevis(100.00)->build();

        $piId = 'pi_partial';

        $s->booking->forceFill([
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => $piId,
            'provider_amount_cents' => 8000,   // 80.00 €
            'platform_fee_cents' => 2000,       // 20.00 €
            'payment_amount_cents' => 10000,    // 100.00 € total
            'currency' => 'EUR',
        ])->save();

        // Seed the wallet earning so the provider starts with 60 € net available.
        $walletService = app(ProviderWalletService::class);
        $walletService->recordEarning($s->booking->fresh());

        // Stub the Stripe refund endpoint. Partial refund of 5000 cents (€50).
        $this->stripe->stub(
            'POST',
            '/v1/refunds',
            StripeFakeResponses::refund('re_partial', $piId, 5000)
        );

        // Call partial refund: amountCents = 5000 (€50)
        app(StripeConnectPaymentService::class)
            ->refundMissionPayment($s->booking->fresh(), 5000);

        // Booking must be 'partially_refunded' (not 'refunded')
        $s->booking->refresh();
        $this->assertSame(
            'partially_refunded',
            $s->booking->payment_status,
            'Partial refund must set payment_status to partially_refunded'
        );

        // Assert the clawback amount is PROPORTIONAL, NOT the raw refund amount.
        $clawback = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)
            ->where('direction', ProviderWalletTransaction::DIRECTION_DEBIT)
            ->first();

        $this->assertNotNull($clawback, 'A clawback must be written for a partial refund');

        $this->assertEqualsWithDelta(
            40.00,
            (float) $clawback->amount,
            0.01,
            'Partial refund clawback must be proportional: 50 × (80/100) = 40 €, NOT the raw 50 €. '
            .'Over-clawing the provider for the platform fee portion is a money correctness bug.'
        );

        // Net balance: +80 earning − 20 platform_fee − 40 clawback = 20 € available.
        $balance = $walletService->balance($s->provider->id);
        $this->assertEqualsWithDelta(
            20.00,
            $balance['available'],
            0.01,
            'After proportional clawback: 80 − 20 − 40 = 20 € net available'
        );
    }

    public function test_service_refund_then_charge_refunded_webhook_claws_back_once(): void
    {
        // Scenario: the normal Stripe flow where:
        //   1. Our code calls refundMissionPayment → creates clawback keyed on re_dedup
        //   2. Stripe fires a charge.refunded webhook → handleChargeRefunded runs
        //      with the same refund id re_dedup in refunds.data[0].id
        // EXPECTED: only ONE clawback in the ledger (idempotency on re_xxx).
        // BUG before fix: webhook uses charge id (ch_yyy) as key → different row → double debit.

        $s = SpineScenario::make()->withDevis(100.00)->build();
        $piId = 'pi_dedup';
        $chargeId = 'ch_dedup';
        $refundId = 're_dedup';

        $s->booking->forceFill([
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => $piId,
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'payment_amount_cents' => 10000,
            'currency' => 'EUR',
        ])->save();

        // Seed earning so wallet is non-empty (easier to spot double debit).
        $walletService = app(ProviderWalletService::class);
        $walletService->recordEarning($s->booking->fresh());

        // ── Step 1: service calls refundMissionPayment (full refund) ──
        // Stub the refund create endpoint to return a known refund id.
        $this->stripe->stub(
            'POST',
            '/v1/refunds',
            StripeFakeResponses::refund($refundId, $piId, 10000)
        );

        app(StripeConnectPaymentService::class)
            ->refundMissionPayment($s->booking->fresh());

        // After service call: 1 clawback keyed on re_dedup.
        $afterService = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)
            ->count();
        $this->assertSame(1, $afterService, 'Service must write exactly 1 clawback');

        // ── Step 2: process charge.refunded webhook with same re_dedup ──
        // Build a realistic charge.refunded payload including refunds.data.
        $chargeRefundedEvent = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_dedup_charge_refunded',
            'type' => 'charge.refunded',
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'received_at' => now(),
            'payload' => [
                'data' => [
                    'object' => [
                        'id' => $chargeId,
                        'object' => 'charge',
                        'payment_intent' => $piId,
                        'amount' => 10000,
                        'amount_refunded' => 10000,
                        'currency' => 'eur',
                        'refunds' => [
                            'object' => 'list',
                            'data' => [
                                [
                                    'id' => $refundId,   // same re_dedup the service used
                                    'object' => 'refund',
                                    'amount' => 10000,
                                    'currency' => 'eur',
                                    'payment_intent' => $piId,
                                    'status' => 'succeeded',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Booking is already 'refunded' after service call, but processor still runs.
        app(StripeWebhookEventProcessor::class)->process($chargeRefundedEvent);

        // ── Assert: still only 1 clawback (no double debit) ──
        $afterWebhook = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)
            ->count();

        $this->assertSame(
            1,
            $afterWebhook,
            'Double-clawback bug: after service refund + charge.refunded webhook both using '
            .'re_dedup, there must be exactly 1 clawback in the ledger. '
            .'The webhook must dedup on the Stripe Refund id, not the Charge id.'
        );
    }

    public function test_two_distinct_partial_refunds_each_claw_back_separately(): void
    {
        // Scenario: €100 booking (80 € provider / 20 € fee).
        // Two distinct partial refunds: €30 then €20.
        //   Refund 1: 3000 × 8000 / 10000 = 2400 cents = €24 clawback
        //   Refund 2: 2000 × 8000 / 10000 = 1600 cents = €16 clawback
        // Total clawed: €40; 2 distinct rows keyed on re_one / re_two.
        //
        // REAL BEHAVIOR NOTE (service-level guard):
        // refundMissionPayment checks payment_status === 'captured' and throws
        // RuntimeException for any other status.  After the first partial refund
        // the status becomes 'partially_refunded', so a direct second call to the
        // service would be blocked.  This is a known limitation: consecutive
        // partial refunds via the service path are not supported without a status
        // reset.  Production multi-partial-refund flows go through the
        // charge.refunded webhook (each re_xxx triggers its own clawback row via
        // recordRefundClawback).
        //
        // We test both clawback amounts by:
        //   - calling refundMissionPayment for the first refund (service path, status → partially_refunded)
        //   - forceFill the booking back to 'captured' to simulate the second admin/webhook-initiated refund
        //   - calling refundMissionPayment again for the second refund
        // This exercises the clawback arithmetic for both calls with distinct refund ids.
        $s = SpineScenario::make()->withDevis(100.00)->build();

        $piId = 'pi_multi';

        $s->booking->forceFill([
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => $piId,
            'provider_amount_cents' => 8000,   // 80.00 €
            'platform_fee_cents' => 2000,       // 20.00 €
            'payment_amount_cents' => 10000,    // 100.00 € total
            'currency' => 'EUR',
        ])->save();

        $walletService = app(ProviderWalletService::class);
        $walletService->recordEarning($s->booking->fresh());

        // ── First partial refund: €30 (3000 cents) → clawback €24 (2400 cents) ──
        $this->stripe->stub(
            'POST',
            '/v1/refunds',
            StripeFakeResponses::refund('re_one', $piId, 3000)
        );

        app(StripeConnectPaymentService::class)
            ->refundMissionPayment($s->booking->fresh(), 3000);

        // Status is now 'partially_refunded' — the service guard would block a
        // second call.  Reset to 'captured' to allow the second partial refund.
        // Use DB::table to bypass any model observers that might interfere.
        DB::table('bookings')
            ->where('id', $s->booking->id)
            ->update(['payment_status' => 'captured']);

        // ── Second partial refund: €20 (2000 cents) → clawback €16 (1600 cents) ──
        $this->stripe->stub(
            'POST',
            '/v1/refunds',
            StripeFakeResponses::refund('re_two', $piId, 2000)
        );

        app(StripeConnectPaymentService::class)
            ->refundMissionPayment($s->booking->fresh(), 2000);

        // ── Assertions ──
        $clawbacks = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)
            ->where('direction', ProviderWalletTransaction::DIRECTION_DEBIT)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $clawbacks, 'Two distinct partial refunds must produce two distinct clawback rows');

        $this->assertEqualsWithDelta(
            24.00,
            (float) $clawbacks->get(0)->amount,
            0.01,
            'First clawback must be €24 (3000 × 8000/10000 = 2400 cents)'
        );

        $this->assertEqualsWithDelta(
            16.00,
            (float) $clawbacks->get(1)->amount,
            0.01,
            'Second clawback must be €16 (2000 × 8000/10000 = 1600 cents)'
        );

        $totalClawed = $clawbacks->sum('amount');
        $this->assertEqualsWithDelta(40.00, (float) $totalClawed, 0.01, 'Total clawed must be €40');

        // Distinct idempotency keys (keyed on re_one / re_two)
        $keys = $clawbacks->pluck('idempotency_key')->unique();
        $this->assertCount(2, $keys, 'Each partial refund clawback must have a distinct idempotency key');
    }

    public function test_clawback_is_idempotent_on_retry(): void
    {
        // Re-calling recordRefundClawback with the same booking+chargeId must not
        // create a second row (idempotency_key guard in ProviderWalletService).
        $s = SpineScenario::make()->withDevis(100.00)->build();

        $s->booking->forceFill([
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => 'pi_f3b',
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'payment_amount_cents' => 10000,
            'currency' => 'EUR',
        ])->save();

        $walletService = app(ProviderWalletService::class);
        $booking = $s->booking->fresh();

        // First call
        $tx1 = $walletService->recordRefundClawback($booking, 80.00, 're_idem_1');
        // Second call — must return the existing row, not create a new one
        $tx2 = $walletService->recordRefundClawback($booking, 80.00, 're_idem_1');

        $this->assertSame($tx1->id, $tx2->id, 'Second clawback call must return the same row (idempotent)');

        $count = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)
            ->count();

        $this->assertSame(1, $count, 'Only one clawback row must exist after two identical calls');
    }
}
