<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\ProviderProfile;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Services\Payments\ProviderWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProviderWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $provider;
    protected User $client;
    protected ProviderWalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $this->provider->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'stripe_connect_account_id' => 'acct_test_xyz',
            'stripe_connect_status' => 'active',
        ]);

        $this->client = User::factory()->client()->create();
        $this->wallet = app(ProviderWalletService::class);
    }

    public function test_balance_returns_zero_when_no_transactions(): void
    {
        $balance = $this->wallet->balance($this->provider->id);

        $this->assertSame(0.0, $balance['available']);
        $this->assertSame(0.0, $balance['pending']);
    }

    public function test_record_earning_creates_credit_and_platform_fee(): void
    {
        $booking = $this->makeBooking(100.0, providerCents: 8500, feeCents: 1500);

        $earning = $this->wallet->recordEarning($booking);

        $this->assertNotNull($earning);
        $this->assertSame('credit', $earning->direction);
        $this->assertEqualsWithDelta(85.0, (float) $earning->amount, 0.01);

        $this->assertSame(1, ProviderWalletTransaction::query()
            ->where('type', ProviderWalletTransaction::TYPE_PLATFORM_FEE)
            ->where('direction', 'debit')
            ->count());

        $balance = $this->wallet->balance($this->provider->id);
        $this->assertEqualsWithDelta(70.0, $balance['available'], 0.01);
    }

    public function test_record_earning_is_idempotent(): void
    {
        $booking = $this->makeBooking(100.0, providerCents: 8000, feeCents: 2000);

        $a = $this->wallet->recordEarning($booking);
        $b = $this->wallet->recordEarning($booking);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, ProviderWalletTransaction::query()
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->count());
    }

    public function test_record_tip_credits_provider(): void
    {
        $booking = $this->makeBooking(100.0);

        $tip = $this->wallet->recordTip($booking, 10.0, 'tip_ref_1');

        $this->assertNotNull($tip);
        $this->assertSame('credit', $tip->direction);
        $this->assertEqualsWithDelta(10.0, (float) $tip->amount, 0.01);
    }

    public function test_record_refund_clawback_debits_provider(): void
    {
        $booking = $this->makeBooking(100.0, providerCents: 8000, feeCents: 2000);
        $this->wallet->recordEarning($booking);

        $clawback = $this->wallet->recordRefundClawback($booking, 80.0, 'ch_test_refund');

        $this->assertSame('debit', $clawback->direction);
        $balance = $this->wallet->balance($this->provider->id);
        $this->assertEqualsWithDelta(0.0, $balance['available'], 0.01);
    }

    public function test_withdraw_rejects_below_minimum(): void
    {
        $this->expectException(ValidationException::class);
        $this->wallet->requestWithdraw($this->provider, 5.0);
    }

    public function test_withdraw_rejects_insufficient_balance(): void
    {
        $this->expectException(ValidationException::class);
        $this->wallet->requestWithdraw($this->provider, 50.0);
    }

    public function test_withdraw_creates_payout_and_pending_debit(): void
    {
        $booking = $this->makeBooking(200.0, providerCents: 20000, feeCents: 0);
        $this->wallet->recordEarning($booking);

        $payout = $this->wallet->requestWithdraw($this->provider, 50.0);

        $this->assertInstanceOf(ProviderPayout::class, $payout);
        $this->assertSame('pending', $payout->status);

        $debit = ProviderWalletTransaction::query()
            ->where('source_type', 'provider_payout')
            ->where('source_id', $payout->id)
            ->first();

        $this->assertNotNull($debit);
        $this->assertSame('processing', $debit->status);
        $this->assertEqualsWithDelta(50.0, (float) $debit->amount, 0.01);
    }

    public function test_mark_payout_cleared_changes_transaction_status(): void
    {
        $booking = $this->makeBooking(200.0, providerCents: 20000, feeCents: 0);
        $this->wallet->recordEarning($booking);
        $payout = $this->wallet->requestWithdraw($this->provider, 50.0);

        $this->wallet->markPayoutCleared($payout, 'po_stripe_999');

        $tx = ProviderWalletTransaction::query()
            ->where('source_id', $payout->id)
            ->where('source_type', 'provider_payout')
            ->first();

        $this->assertSame('cleared', $tx->status);
        $this->assertSame('po_stripe_999', $tx->stripe_payout_id);
    }

    public function test_reverse_payout_marks_transaction_reversed(): void
    {
        $booking = $this->makeBooking(200.0, providerCents: 20000, feeCents: 0);
        $this->wallet->recordEarning($booking);
        $payout = $this->wallet->requestWithdraw($this->provider, 50.0);

        $this->wallet->reversePayout($payout, 'bank_decline');

        $tx = ProviderWalletTransaction::query()
            ->where('source_id', $payout->id)
            ->where('source_type', 'provider_payout')
            ->first();

        $this->assertSame('reversed', $tx->status);
    }

    protected function makeBooking(float $amount, ?int $providerCents = null, ?int $feeCents = null): Booking
    {
        return Booking::create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => $amount,
            'currency' => 'EUR',
            'platform_fee_cents' => $feeCents,
            'provider_amount_cents' => $providerCents,
            'stripe_payment_intent_id' => 'pi_test_' . random_int(1000, 9999),
        ]);
    }
}
