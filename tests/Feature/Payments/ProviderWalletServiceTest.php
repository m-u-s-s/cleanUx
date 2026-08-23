<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Mission;
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

    public function test_record_earning_credits_net_without_separate_fee_debit(): void
    {
        // M4 — provider_amount_cents is already net (total − commission); the wallet must hold
        // exactly that, with NO separate platform_fee debit (which previously double-deducted).
        $booking = $this->makeBooking(100.0, providerCents: 8500, feeCents: 1500);

        $earning = $this->wallet->recordEarning($booking);

        $this->assertNotNull($earning);
        $this->assertSame('credit', $earning->direction);
        $this->assertEqualsWithDelta(85.0, (float) $earning->amount, 0.01);

        $this->assertSame(0, ProviderWalletTransaction::query()
            ->where('type', ProviderWalletTransaction::TYPE_PLATFORM_FEE)
            ->where('direction', 'debit')
            ->count(), 'no separate platform_fee debit must be written');

        $balance = $this->wallet->balance($this->provider->id);
        $this->assertEqualsWithDelta(85.0, $balance['available'], 0.01);
    }

    public function test_m4_reversal_migration_corrects_legacy_double_deduction(): void
    {
        // Simulate a legacy row pair: net earning credit + the erroneous platform_fee debit.
        ProviderWalletTransaction::create([
            'provider_user_id' => $this->provider->id,
            'type' => ProviderWalletTransaction::TYPE_EARNING,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
            'amount' => 85.0, 'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'idempotency_key' => 'legacy:earning:1', 'occurred_at' => now(),
        ]);
        ProviderWalletTransaction::create([
            'provider_user_id' => $this->provider->id,
            'type' => ProviderWalletTransaction::TYPE_PLATFORM_FEE,
            'direction' => ProviderWalletTransaction::DIRECTION_DEBIT,
            'amount' => 15.0, 'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'idempotency_key' => 'legacy:earning:1:platform_fee', 'occurred_at' => now(),
        ]);

        // Before: 85 − 15 = 70 (buggy).
        $this->assertEqualsWithDelta(70.0, $this->wallet->balance($this->provider->id)['available'], 0.01);

        $migration = require base_path('database/migrations/2026_06_08_000004_reverse_double_platform_fee_debits.php');
        $migration->up();
        $migration->up(); // idempotent — second run adds nothing

        // After: the debit is reversed by an adjustment_credit → balance back to net 85.
        $this->assertEqualsWithDelta(85.0, $this->wallet->balance($this->provider->id)['available'], 0.01);
        $this->assertSame(1, ProviderWalletTransaction::query()
            ->where('type', ProviderWalletTransaction::TYPE_ADJUSTMENT_CREDIT)
            ->where('idempotency_key', 'like', '%:m4_reversal')
            ->count());
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

    /** C'EST L'INTERVENANT QUI EST CRÉDITÉ, PAS LE NOM RESTÉ SUR LA COMMANDE. */
    public function test_c_est_l_intervenant_de_la_mission_qui_est_credite(): void
    {
        $booking = $this->makeBooking(200.0, providerCents: 16000, feeCents: 4000);

        // La réservation garde le nom d'origine ; la mission désigne quelqu'un d'autre.
        $ancien = User::factory()->employe()->create();
        $booking->forceFill(['employe_id' => $ancien->id])->save();

        Mission::factory()->create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->provider->id,
        ]);

        $gain = $this->wallet->recordEarning($booking->fresh());

        $this->assertNotNull($gain);
        $this->assertSame(
            $this->provider->id,
            (int) $gain->provider_user_id,
            'Le portefeuille crédite le nom resté sur la commande au lieu de celui qui est intervenu.',
        );

        $this->assertEqualsWithDelta(
            0.0,
            $this->wallet->balance($ancien->id)['available'],
            0.01,
            'L’ancien intervenant a été crédité d’une mission qu’il n’a pas faite.',
        );
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

    /** UN RETRAIT ENGAGÉ DOIT RÉDUIRE LE SOLDE DISPONIBLE. */
    public function test_le_solde_disponible_baisse_apres_un_retrait(): void
    {
        $booking = $this->makeBooking(200.0, providerCents: 20000, feeCents: 0);
        $this->wallet->recordEarning($booking);

        $this->assertEqualsWithDelta(
            200.0,
            $this->wallet->balance($this->provider->id)['available'],
            0.01,
        );

        $this->wallet->requestWithdraw($this->provider, 150.0);

        $this->assertEqualsWithDelta(
            50.0,
            $this->wallet->balance($this->provider->id)['available'],
            0.01,
            'Un retrait engagé doit réduire le solde disponible dès sa demande.',
        );
    }

    /** LE MÊME SOLDE NE PEUT PAS ÊTRE RETIRÉ DEUX FOIS. */
    public function test_le_meme_solde_ne_peut_pas_etre_retire_deux_fois(): void
    {
        $booking = $this->makeBooking(200.0, providerCents: 20000, feeCents: 0);
        $this->wallet->recordEarning($booking);

        $this->wallet->requestWithdraw($this->provider, 150.0);

        // 150 + 150 = 300 demandés sur 200 gagnés : le second retrait doit être refusé.
        $this->expectException(ValidationException::class);
        $this->wallet->requestWithdraw($this->provider, 150.0);
    }

    /** Un retrait annulé rend le solde : seul un débit REVERSED cesse d'engager. */
    public function test_un_retrait_annule_rend_le_solde(): void
    {
        $booking = $this->makeBooking(200.0, providerCents: 20000, feeCents: 0);
        $this->wallet->recordEarning($booking);

        $payout = $this->wallet->requestWithdraw($this->provider, 150.0);
        $this->wallet->reversePayout($payout, 'test');

        $this->assertEqualsWithDelta(
            200.0,
            $this->wallet->balance($this->provider->id)['available'],
            0.01,
        );
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
        return Booking::forceCreate([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => $amount,
            'currency' => 'EUR',
            'platform_fee_cents' => $feeCents,
            'provider_amount_cents' => $providerCents,
            'stripe_payment_intent_id' => 'pi_test_'.random_int(1000, 9999),
        ]);
    }
}
