<?php

namespace Tests\Feature\Spine;

use App\Models\BookingTip;
use App\Models\ProviderWalletTransaction;
use App\Services\Payments\ProviderWalletService;
use App\Services\Tips\TipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * F10 — Tip lifecycle: confirmCharge idempotency, markPaidOut wallet credit,
 * cancel guard prevents payout.
 *
 * FINDING: TipService::markPaidOut() does NOT call ProviderWalletService::recordTip.
 * It only updates the BookingTip status to paid_out. The wallet credit via
 * recordTip is a separate action that must be called explicitly (e.g. by a job
 * or a webhook handler). Tests reflect this real behavior.
 *
 * FINDING: TipService::cancel() only allows cancellation of PENDING tips.
 * Charged or paid_out tips cannot be cancelled via cancel() — throws ValidationException.
 */
class TipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a completed booking scenario and return a pending tip on it.
     */
    private function makePendingTip(int $amountCents = 1000): array
    {
        $s = SpineScenario::make()->withDevis(100.00)->build();

        // Tips require the booking to be in a completed status.
        config(['tips.eligible_booking_statuses' => ['termine', 'completed', 'closed']]);
        $s->booking->forceFill([
            'status' => 'termine',
            'payment_amount_cents' => 10000,
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
            'currency' => 'EUR',
        ])->save();

        $tip = app(TipService::class)->create(
            client: $s->client,
            booking: $s->booking->fresh(),
            amountCents: $amountCents,
        );

        return [$s, $tip];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1 — confirmCharge is idempotent
    // ─────────────────────────────────────────────────────────────────────────

    public function test_confirm_charge_is_idempotent(): void
    {
        [$s, $tip] = $this->makePendingTip(1000);

        $service = app(TipService::class);

        // First confirmCharge: pending → charged.
        $charged1 = $service->confirmCharge($tip, 'pi_tip_idem');
        $this->assertSame(BookingTip::STATUS_CHARGED, $charged1->status);
        $this->assertNotNull($charged1->charged_at);

        // Second confirmCharge on the already-charged tip: must return the same tip,
        // no re-charge, status stays charged.
        $charged2 = $service->confirmCharge($charged1->fresh(), 'pi_tip_idem_2nd');
        $this->assertSame(BookingTip::STATUS_CHARGED, $charged2->status, 'Second confirmCharge must leave status=charged');

        // Only one BookingTip must exist.
        $count = BookingTip::query()->where('booking_id', $s->booking->id)->count();
        $this->assertSame(1, $count, 'No duplicate tip row must be created on repeated confirmCharge');

        // charged_at from first call must be preserved (not overwritten).
        $this->assertEquals(
            $charged1->charged_at->format('Y-m-d H:i:s'),
            $charged2->fresh()->charged_at->format('Y-m-d H:i:s'),
            'charged_at must not be overwritten on idempotent confirmCharge'
        );
    }

    public function test_confirm_charge_on_paid_out_tip_is_idempotent(): void
    {
        [$s, $tip] = $this->makePendingTip(1500);
        $service = app(TipService::class);

        // Advance to paid_out.
        $charged = $service->confirmCharge($tip, 'pi_tip_po');
        $paidOut = $service->markPaidOut($charged->fresh(), 'tr_tip_po');

        $this->assertSame(BookingTip::STATUS_PAID_OUT, $paidOut->status);

        // confirmCharge on a paid_out tip must return it without error or status change.
        $result = $service->confirmCharge($paidOut->fresh(), 'pi_tip_po_2nd');
        $this->assertSame(BookingTip::STATUS_PAID_OUT, $result->status, 'confirmCharge on paid_out must be a no-op');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2 — markPaidOut transitions status correctly; wallet recordTip credits
    // ─────────────────────────────────────────────────────────────────────────

    public function test_mark_paid_out_transitions_status_from_charged(): void
    {
        [$s, $tip] = $this->makePendingTip(2000);
        $service = app(TipService::class);

        $charged = $service->confirmCharge($tip, 'pi_tip_payout');
        $this->assertSame(BookingTip::STATUS_CHARGED, $charged->status);

        $paidOut = $service->markPaidOut($charged->fresh(), 'tr_tip_payout_123');
        $this->assertSame(BookingTip::STATUS_PAID_OUT, $paidOut->status);
        $this->assertNotNull($paidOut->paid_out_at, 'paid_out_at must be set');
        $this->assertSame('tr_tip_payout_123', $paidOut->stripe_transfer_id);
    }

    public function test_wallet_record_tip_credits_provider_exactly_once(): void
    {
        [$s, $tip] = $this->makePendingTip(2000);   // 2000 cents = 20.00 €
        $service = app(TipService::class);
        $walletService = app(ProviderWalletService::class);

        $charged = $service->confirmCharge($tip, 'pi_tip_wallet');
        $service->markPaidOut($charged->fresh(), 'tr_tip_wallet');

        // markPaidOut does NOT call recordTip — the wallet credit is a separate action.
        // We simulate calling recordTip as the payout job / webhook handler would.
        $booking = $s->booking->fresh();
        $tx1 = $walletService->recordTip($booking, 20.00, 'tip_ref_'.$tip->id);
        $this->assertNotNull($tx1, 'recordTip must return a wallet transaction');
        $this->assertSame(ProviderWalletTransaction::TYPE_TIP, $tx1->type);
        $this->assertSame(ProviderWalletTransaction::DIRECTION_CREDIT, $tx1->direction);
        $this->assertEqualsWithDelta(20.00, (float) $tx1->amount, 0.01, 'Tip wallet credit must match tip amount');
        $this->assertSame(ProviderWalletTransaction::STATUS_AVAILABLE, $tx1->status);

        // Idempotency: calling recordTip again with the same sourceRef must return the same row.
        $tx2 = $walletService->recordTip($booking, 20.00, 'tip_ref_'.$tip->id);
        $this->assertSame($tx1->id, $tx2->id, 'recordTip must be idempotent on same sourceRef');

        // Exactly one tip wallet transaction must exist.
        $tipTxCount = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_TIP)
            ->count();
        $this->assertSame(1, $tipTxCount, 'Only one tip wallet credit must exist after idempotent recordTip calls');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3 — cancelled tip cannot be paid out; no wallet credit
    // ─────────────────────────────────────────────────────────────────────────

    public function test_cancel_pending_tip_sets_status_cancelled(): void
    {
        [$s, $tip] = $this->makePendingTip(1500);
        $service = app(TipService::class);

        $cancelled = $service->cancel($tip);
        $this->assertSame(BookingTip::STATUS_CANCELLED, $cancelled->status, 'cancel() must set status=cancelled');
    }

    public function test_cancelled_tip_cannot_be_charged(): void
    {
        [$s, $tip] = $this->makePendingTip(1500);
        $service = app(TipService::class);

        $cancelled = $service->cancel($tip);

        // Attempting confirmCharge on a cancelled tip must throw ValidationException.
        $this->expectException(ValidationException::class);
        $service->confirmCharge($cancelled->fresh());
    }

    public function test_cancelled_tip_does_not_produce_wallet_credit(): void
    {
        [$s, $tip] = $this->makePendingTip(1500);
        $service = app(TipService::class);
        $walletService = app(ProviderWalletService::class);

        // Cancel the tip.
        $service->cancel($tip);

        // A cancelled tip must not generate a wallet tip credit —
        // neither via markPaidOut (which would throw on non-charged status)
        // nor via recordTip (no one should call this for a cancelled tip).
        $tipTxCount = ProviderWalletTransaction::query()
            ->where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_TIP)
            ->count();

        $this->assertSame(
            0,
            $tipTxCount,
            'A cancelled tip must not produce any provider wallet tip credit'
        );
    }

    public function test_cancel_charged_tip_throws(): void
    {
        // cancel() only works on pending tips. Attempting to cancel a charged tip
        // must throw ValidationException — no money should move.
        [$s, $tip] = $this->makePendingTip(1000);
        $service = app(TipService::class);

        $charged = $service->confirmCharge($tip);

        $this->expectException(ValidationException::class);
        $service->cancel($charged->fresh());
    }

    public function test_mark_paid_out_requires_charged_status(): void
    {
        [$s, $tip] = $this->makePendingTip(1000);
        $service = app(TipService::class);

        // Attempting markPaidOut on a pending tip (not yet charged) must throw.
        $this->expectException(ValidationException::class);
        $service->markPaidOut($tip);
    }
}
