<?php

namespace Tests\Feature\Integration;

use App\Models\Booking;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use App\Support\Webhooks\BusinessEventEmitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/** M22 — genuinely test the chain Stripe webhook event → StripeWebhookEventProcessor → BusinessEventEmitter (the previous version bypassed the processor and called the emitter directly, so a processor that stopped emitting would still pass). */
class StripePaymentWebhookChainTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('webhooks_v2.enabled', true);
        Config::set('webhooks_v2.allowed_events', ['payment.succeeded', 'payment.failed', 'payment.refunded']);
        Config::set('accounting_v2.auto_post_enabled', false);
        Config::set(['cashier.secret' => 'sk_test_fake', 'services.stripe.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    protected function makeBooking(string $piId, string $paymentStatus = 'pending'): Booking
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id, 'status' => 'en_attente']);
        $booking->forceFill([
            'stripe_payment_intent_id' => $piId,
            'payment_status' => $paymentStatus,
        ])->save();

        return $booking->fresh();
    }

    protected function process(string $type, array $object): void
    {
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_'.uniqid(),
            'type' => $type,
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'payload' => ['data' => ['object' => $object]],
            'received_at' => now(),
        ]);

        app(StripeWebhookEventProcessor::class)->process($event);
    }

    public function test_payment_succeeded_emits_business_webhook_through_processor(): void
    {
        $booking = $this->makeBooking('pi_succ_001');

        // syncPaymentIntent retrieves the PI; stub it as succeeded so the booking captures.
        $this->stripe->stub('GET', '/v1/payment_intents/pi_succ_001',
            StripeFakeResponses::paymentIntent('pi_succ_001', 'succeeded'));

        $this->process('payment_intent.succeeded', [
            'id' => 'pi_succ_001',
            'amount' => 12000,
            'currency' => 'eur',
        ]);

        $this->assertSame('captured', $booking->fresh()->payment_status);
        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'payment.succeeded')->where('source_id', $booking->id)->count());
    }

    public function test_payment_failed_emits_business_webhook_through_processor(): void
    {
        $booking = $this->makeBooking('pi_fail_001');

        $this->process('payment_intent.payment_failed', [
            'id' => 'pi_fail_001',
            'last_payment_error' => ['message' => 'Card declined', 'code' => 'card_declined'],
        ]);

        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'payment.failed')->where('source_id', $booking->id)->count());
    }

    public function test_payment_refunded_emits_business_webhook_through_processor(): void
    {
        $booking = $this->makeBooking('pi_ref_001', 'captured');

        $this->process('charge.refunded', [
            'id' => 'ch_ref_001',
            'payment_intent' => 'pi_ref_001',
            'amount' => 12000,
            'amount_refunded' => 12000,
        ]);

        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'payment.refunded')->where('source_id', $booking->id)->count());
    }

    public function test_business_emitter_skips_when_webhook_module_disabled(): void
    {
        Config::set('webhooks_v2.enabled', false);
        $booking = $this->makeBooking('pi_off_001');

        BusinessEventEmitter::emit(
            eventCode: 'payment.succeeded',
            payload: ['booking_id' => $booking->id],
            idempotencyKey: 'payment.succeeded:pi_off_001',
        );

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_business_emitter_skips_unwhitelisted_event(): void
    {
        Config::set('webhooks_v2.allowed_events', ['payment.succeeded']);

        BusinessEventEmitter::emit(
            eventCode: 'payment.unauthorized',
            payload: ['booking_id' => 1],
        );

        $this->assertSame(0, WebhookEvent::query()->count());
    }
}
