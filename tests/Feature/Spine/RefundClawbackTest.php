<?php

namespace Tests\Feature\Spine;

use App\Models\ProviderWalletTransaction;
use App\Services\Payments\ProviderWalletService;
use App\Services\Payments\StripeConnectPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            StripeFakeResponses::refund('re_f3', $piId, 8000)
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
