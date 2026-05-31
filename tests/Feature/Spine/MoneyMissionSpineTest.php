<?php

namespace Tests\Feature\Spine;

use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class MoneyMissionSpineTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        // Set the config so service constructors (MissionPaymentService,
        // StripeConnectPaymentService) that call Stripe::setApiKey(config('cashier.secret'))
        // use our fake key instead of null.
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

    public function test_full_spine_book_to_settle(): void
    {
        $s = SpineScenario::make()->withDevis(100.00)->build();
        $piId = 'pi_spine_1';

        // 1. Authorize (pre-auth)
        $this->stripe->stub('POST', '/v1/payment_intents', StripeFakeResponses::paymentIntent($piId, 'requires_capture'));
        app(MissionPaymentService::class)->authorize($s->booking, 'pm_card_visa');
        $s->booking->refresh();
        $this->assertSame('authorized', $s->booking->payment_status);
        $this->assertSame($piId, $s->booking->stripe_payment_intent_id);
        $this->assertSame(8000, (int) $s->booking->provider_amount_cents);
        $this->assertSame(2000, (int) $s->booking->platform_fee_cents);

        // 2. Capture + payout entry
        // The capture stub returns 'succeeded' so that:
        //   - captureMissionPayment() sets booking.payment_status='captured'
        //   - the fake client stores the succeeded PI in lastKnown for the later
        //     GET retrieve inside syncPaymentIntent (called by the webhook handler)
        $this->stripe->stub('POST', "/v1/payment_intents/{$piId}/capture", StripeFakeResponses::paymentIntent($piId, 'succeeded'));
        $payout = app(StripeConnectPaymentService::class)->captureMissionPayment($s->mission->fresh());
        $s->booking->refresh();
        $this->assertSame('captured', $s->booking->payment_status);
        $this->assertInstanceOf(ProviderPayout::class, $payout);
        $this->assertSame($s->provider->id, $payout->provider_user_id);
        $this->assertEqualsWithDelta(80.00, (float) $payout->amount, 0.001);
        $this->assertSame(ProviderPayout::STATUS_PENDING, $payout->status);

        // 3. payment_intent.succeeded webhook → wallet credit
        //
        // Booking lookup: handlePaymentIntentSucceeded locates the booking via
        //   Booking::where('stripe_payment_intent_id', $piId)
        // No metadata key required — the PI id in the event object is enough.
        //
        // StripeWebhookEvent required fillable columns (from $fillable):
        //   stripe_event_id, type, status, payload
        // 'payload' is cast to array; the processor reads payload['data']['object'].
        //
        // syncPaymentIntent does PaymentIntent::retrieve($piId) GET.
        // The fake client's lastKnown already holds the 'succeeded' PI body
        // after the capture stub above (FakeStripeHttpClient::rememberLastKnown
        // strips the /capture suffix and stores under /v1/payment_intents/{id}).
        // syncPaymentIntent maps 'succeeded' → booking.payment_status='captured'.
        //
        // Guard in handler: recordEarning is called when
        //   $booking->payment_status === 'captured' && $previousStatus !== 'captured'
        // Because captureMissionPayment already set payment_status='captured',
        // previousStatus will equal 'captured' and the guard would skip recordEarning.
        //
        // FIX APPLIED (production): the guard now always calls recordEarning when
        // status is 'captured'; ProviderWalletService::recordEarning is idempotent
        // via idempotency_key so double-credits are impossible.
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_spine_1',
            'type' => 'payment_intent.succeeded',
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'payload' => ['data' => ['object' => StripeFakeResponses::paymentIntent($piId, 'succeeded')]],
            'received_at' => now(),   // NOT NULL column required by the stripe_webhook_events table
        ]);
        app(StripeWebhookEventProcessor::class)->process($event);

        $credit = ProviderWalletTransaction::where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->first();
        $this->assertNotNull($credit, 'wallet earning must be recorded by the succeeded webhook');
        $this->assertEqualsWithDelta(80.00, (float) $credit->amount, 0.001);
    }
}
