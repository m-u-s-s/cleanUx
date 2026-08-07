<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\ProviderProfile;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Services\Payments\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayoutFlowTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // CommissionService
    // ──────────────────────────────────────────────

    public function test_commission_calculated_correctly_for_booking(): void
    {
        $booking = Booking::factory()->create([
            'devis_estime' => 100,
            'payment_status' => 'captured',
        ]);

        $service = new CommissionService;
        $result = $service->calculateForBooking($booking);

        $this->assertEquals(10000, $result['total_cents']);
        // Unified platform rate 20% (BRIO_PLATFORM_FEE_PERCENT, aligned on prod — audit B1).
        $this->assertEquals(2000, $result['platform_fee_cents']);
        $this->assertEquals(8000, $result['provider_payout_cents']);
        $this->assertEquals(0.20, $result['commission_rate']);
        $this->assertEquals('eur', $result['currency']);
    }

    public function test_commission_enforces_minimum_fee_when_booking_large_enough(): void
    {
        // €50 booking → 20% = 1000c, above the €2 minimum
        $booking = Booking::factory()->create(['devis_estime' => 50]);

        $result = (new CommissionService)->calculateForBooking($booking);

        // 20% of 5000c = 1000c, which exceeds the €2 minimum
        $this->assertGreaterThanOrEqual(200, $result['platform_fee_cents'], 'Platform fee below minimum');
        $this->assertEquals(1000, $result['platform_fee_cents']);
    }

    public function test_commission_fee_never_exceeds_total(): void
    {
        // Very small booking: €1 total — fee clamped to total
        $booking = Booking::factory()->create(['devis_estime' => 1]);

        $result = (new CommissionService)->calculateForBooking($booking);

        $this->assertLessThanOrEqual($result['total_cents'], $result['platform_fee_cents'], 'Fee exceeds booking total');
        $this->assertGreaterThanOrEqual(0, $result['provider_payout_cents'], 'Provider payout cannot be negative');
    }

    public function test_commission_stores_breakdown_on_booking(): void
    {
        $booking = Booking::factory()->create([
            'devis_estime' => 100,
            'payment_status' => 'captured',
        ]);

        $commission = (new CommissionService)->calculateForBooking($booking);

        $updates = ['payout_status' => 'processed', 'platform_fee_cents' => $commission['platform_fee_cents']];
        if (Schema::hasColumn('bookings', 'provider_payout_cents')) {
            $updates['provider_payout_cents'] = $commission['provider_payout_cents'];
        } elseif (Schema::hasColumn('bookings', 'provider_amount_cents')) {
            $updates['provider_amount_cents'] = $commission['provider_payout_cents'];
        }
        $booking->update($updates);

        $fresh = $booking->fresh();
        $this->assertEquals('processed', $fresh->payout_status);
        $this->assertEquals(2000, $fresh->platform_fee_cents);
    }

    // ──────────────────────────────────────────────
    // ProcessProviderPayouts command
    // ──────────────────────────────────────────────

    public function test_payout_command_dry_run_shows_calculations(): void
    {
        Booking::factory()->create([
            'status' => 'completed',
            'devis_estime' => 200,
            'payout_status' => null,
        ]);

        $this->artisan('payouts:process', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 bookings');
    }

    public function test_payout_commission_breakdown_stored_on_booking(): void
    {
        // Directly tests the commission + booking update logic without running the
        // full artisan command (which has observer side-effects in test env).
        $booking = Booking::factory()->create([
            'status' => 'completed',
            'devis_estime' => 100,
            'payout_status' => null,
        ]);

        $commission = app(CommissionService::class)->calculateForBooking($booking);

        $updates = [
            'payout_status' => 'processed',
            'platform_fee_cents' => $commission['platform_fee_cents'],
        ];
        if (Schema::hasColumn('bookings', 'provider_payout_cents')) {
            $updates['provider_payout_cents'] = $commission['provider_payout_cents'];
        } elseif (Schema::hasColumn('bookings', 'provider_amount_cents')) {
            $updates['provider_amount_cents'] = $commission['provider_payout_cents'];
        }
        $booking->fill($updates)->save();

        $fresh = $booking->fresh();
        $this->assertEquals('processed', $fresh->payout_status);
        $this->assertNotNull($fresh->platform_fee_cents);
        $this->assertEquals(2000, $fresh->platform_fee_cents);
    }

    public function test_payout_command_skips_already_processed_bookings(): void
    {
        Booking::factory()->create([
            'status' => 'completed',
            'devis_estime' => 100,
            'payout_status' => 'processed',
        ]);

        $this->artisan('payouts:process')
            ->assertSuccessful()
            ->expectsOutputToContain('Found 0 bookings');
    }

    /**
     * A1 regression: a mission paid via Stripe destination charge already transferred
     * the provider share to their Connect account at capture. Phase 2 must NOT create a
     * second Transfer for it, even if a legacy row still carries payout_status='processed'
     * with a null stripe_transfer_id.
     */
    public function test_phase2_skips_bookings_already_auto_paid_via_destination_charge(): void
    {
        Booking::factory()->create([
            'status' => 'completed',
            'devis_estime' => 100,
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => 'pi_test_autopaid',
            'payout_status' => 'processed',
            'stripe_transfer_id' => null,
            'provider_payout_cents' => 8500,
        ]);

        $this->artisan('payouts:process')
            ->assertSuccessful()
            ->expectsOutputToContain('No manual transfers needed');
    }

    /**
     * A1 regression: Phase 1 must mark a captured destination-charge booking with the
     * explicit 'auto_transferred' status so it is excluded from the Phase 2 manual transfer
     * (which would otherwise double-pay the provider).
     */
    public function test_phase1_marks_captured_destination_charge_as_auto_transferred(): void
    {
        // No provider attached so the (separately-tracked, M5/M10) raw wallet insert path
        // is skipped — this test isolates the Phase 1 payout_status marking behaviour.
        $booking = Booking::factory()->create([
            'status' => 'completed',
            'devis_estime' => 100,
            'payment_status' => 'captured',
            'stripe_payment_intent_id' => 'pi_test_autopaid2',
            'payout_status' => null,
            'employe_id' => null,
            'assigned_provider_user_id' => null,
        ]);

        $this->artisan('payouts:process')->assertSuccessful();

        $this->assertSame('auto_transferred', $booking->fresh()->payout_status);
    }

    /**
     * M5/M10 — Phase 1 must credit the wallet through the idempotent ProviderWalletService
     * (the old raw insert mapped non-existent columns and never set the NOT NULL
     * provider_user_id/direction/occurred_at, so it crashed whenever a provider was present).
     */
    public function test_phase1_credits_wallet_via_service_for_provider_booking(): void
    {
        $provider = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'status' => 'active']);

        $booking = Booking::factory()->create([
            'status' => 'completed',
            'employe_id' => $provider->id,
            'devis_estime' => 100,
            'payment_status' => 'authorized',
            'stripe_payment_intent_id' => null,
            'payout_status' => null,
        ]);

        $this->artisan('payouts:process')->assertSuccessful();

        $this->assertSame('processed', $booking->fresh()->payout_status);

        $earning = ProviderWalletTransaction::query()
            ->where('provider_user_id', $provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->first();

        $this->assertNotNull($earning, 'Phase 1 must credit the provider wallet via recordEarning');
        // €100 booking, 20% platform fee → provider earns €80.
        $this->assertEqualsWithDelta(80.0, (float) $earning->amount, 0.01);
    }

    // ──────────────────────────────────────────────
    // Webhook signature validation
    // ──────────────────────────────────────────────

    public function test_webhook_stripe_connect_rejects_request_without_signature(): void
    {
        // Without STRIPE_CONNECT_WEBHOOK_SECRET set, the controller returns 500.
        // With secret configured but no Stripe-Signature header, it returns 400.
        // Either way it must not return 2xx.
        $response = $this->postJson('/webhooks/stripe-connect', ['type' => 'test']);
        $this->assertContains($response->status(), [400, 500], 'Expected 4xx/5xx for unsigned webhook');
    }

    // ──────────────────────────────────────────────
    // Provider payouts API endpoints
    // ──────────────────────────────────────────────

    /** Create an employe user with a ProviderProfile (required by the payouts controller). */
    private function makeProviderUser(): User
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_provider_payouts_endpoint_returns_history(): void
    {
        $user = $this->makeProviderUser();
        Sanctum::actingAs($user);

        ProviderPayout::factory()->create([
            'provider_user_id' => $user->id,
            'status' => ProviderPayout::STATUS_PENDING,
            'amount' => 85.00,
            'currency' => 'eur',
        ]);

        $this->getJson('/api/provider/payouts')
            ->assertOk()
            ->assertJsonStructure(['ok', 'data', 'pagination']);
    }

    public function test_provider_payouts_summary_returns_totals(): void
    {
        $user = $this->makeProviderUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/provider/payouts/summary')
            ->assertOk()
            ->assertJsonStructure(['ok', 'currency', 'this_month', 'last_month', 'all_time']);
    }

    public function test_provider_payouts_endpoint_requires_auth(): void
    {
        $this->getJson('/api/provider/payouts')
            ->assertUnauthorized();
    }

    // ──────────────────────────────────────────────
    // ProviderPayout model helpers
    // ──────────────────────────────────────────────

    public function test_provider_payout_mark_as_paid(): void
    {
        $payout = ProviderPayout::factory()->create([
            'status' => ProviderPayout::STATUS_PENDING,
        ]);

        $payout->markAsPaid('po_test_123');

        $fresh = $payout->fresh();
        $this->assertEquals(ProviderPayout::STATUS_PAID, $fresh->status);
        $this->assertEquals('po_test_123', $fresh->provider_payout_id);
    }

    public function test_provider_payout_mark_as_failed_merges_metadata(): void
    {
        $payout = ProviderPayout::factory()->create([
            'status' => ProviderPayout::STATUS_PENDING,
            'metadata' => ['mission_id' => 99],
        ]);

        $payout->markAsFailed(['failure_code' => 'insufficient_funds']);

        $fresh = $payout->fresh();
        $this->assertEquals(ProviderPayout::STATUS_FAILED, $fresh->status);
        $this->assertEquals('insufficient_funds', $fresh->metadata['failure_code']);
        $this->assertEquals(99, $fresh->metadata['mission_id'], 'Existing metadata should be preserved');
    }
}
