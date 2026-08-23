<?php

namespace Tests\Feature\Spine;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/** A2 — The manual payout Transfer::create in payouts:process Phase 2 must send a deterministic Stripe idempotency key so a crash/retry between Stripe success and the local DB update cannot create a second real transfer (double payment to the provider). */
class PayoutIdempotencyTest extends TestCase
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

    public function test_phase2_manual_transfer_sends_deterministic_idempotency_key(): void
    {
        // A provider with a Connect account who is owed a manual transfer (the booking
        // was NOT paid via a destination charge: no captured PaymentIntent).
        $provider = User::factory()->employe()->create([
            'stripe_connect_account_id' => 'acct_manual_payout_test',
            'stripe_connect_status' => 'active',
        ]);
        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'stripe_connect_account_id' => 'acct_manual_payout_test',
            'stripe_connect_status' => 'active',
        ]);

        $booking = Booking::factory()->create([
            'status' => 'completed',
            'employe_id' => $provider->id,
            'devis_estime' => 100,
            'payment_status' => 'authorized',   // NOT captured → genuine manual transfer
            'stripe_payment_intent_id' => null,
            'payout_status' => 'processed',
            'stripe_transfer_id' => null,
            'provider_payout_cents' => 8500,
        ]);

        $this->stripe->stub(
            'POST',
            '/v1/transfers',
            StripeFakeResponses::transfer('tr_test_a2', 'acct_manual_payout_test', 8500)
        );

        $this->artisan('payouts:process')->assertSuccessful();

        // The transfer must have been attempted with the deterministic idempotency key.
        $this->assertContains('POST /v1/transfers', $this->stripe->calls(), 'Phase 2 must issue the manual transfer');
        $this->assertSame(
            'payout:booking:'.$booking->id,
            $this->stripe->idempotencyKeyFor('POST', '/v1/transfers'),
            'A2: Transfer::create must send a deterministic Idempotency-Key so a retry cannot double-pay'
        );

        // And the booking is marked transferred with the returned transfer id.
        $fresh = $booking->fresh();
        $this->assertSame('tr_test_a2', $fresh->stripe_transfer_id);
        $this->assertSame('transferred', $fresh->payout_status);
    }
}
