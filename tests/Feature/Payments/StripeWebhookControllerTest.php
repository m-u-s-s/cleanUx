<?php

namespace Tests\Feature\Payments;

use App\Jobs\Payments\ProcessStripeWebhookJob;
use App\Models\StripeWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Stripe\Webhook;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.connect_webhook_secret' => 'whsec_test_secret']);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $response = $this->postJson('/webhooks/stripe-connect',
            ['data' => 'test'],
            ['Stripe-Signature' => 'invalid']
        );

        $response->assertStatus(400);
        $this->assertSame(0, StripeWebhookEvent::count());
    }

    public function test_missing_secret_returns_500(): void
    {
        config(['services.stripe.connect_webhook_secret' => null]);
        // Override env() default in controller path
        putenv('STRIPE_CONNECT_WEBHOOK_SECRET');

        $response = $this->postJson('/webhooks/stripe-connect',
            ['data' => 'test'],
            ['Stripe-Signature' => 't=1,v1=fake']
        );

        $response->assertStatus(500);
    }

    public function test_valid_event_is_stored_and_dispatched(): void
    {
        Queue::fake();

        $payload = json_encode([
            'id' => 'evt_test_valid',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
            'account' => null,
        ]);

        $signature = $this->makeStripeSignature($payload, 'whsec_test_secret');

        $response = $this->call(
            'POST',
            '/webhooks/stripe-connect',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $signature],
            $payload
        );

        $response->assertOk();
        $response->assertJson(['ok' => true, 'dispatched' => true]);

        $stored = StripeWebhookEvent::where('stripe_event_id', 'evt_test_valid')->first();
        $this->assertNotNull($stored);
        $this->assertSame('payment_intent.succeeded', $stored->type);

        Queue::assertPushed(ProcessStripeWebhookJob::class);
    }

    public function test_replayed_event_is_not_redispatched(): void
    {
        Queue::fake();

        $payload = json_encode([
            'id' => 'evt_test_replay',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test']],
            'account' => null,
        ]);

        $signature = $this->makeStripeSignature($payload, 'whsec_test_secret');

        // 1st call
        $this->call('POST', '/webhooks/stripe-connect', [], [], [],
            ['HTTP_Stripe-Signature' => $signature], $payload);

        // Mark as processed to simulate completed work
        StripeWebhookEvent::where('stripe_event_id', 'evt_test_replay')
            ->update(['status' => StripeWebhookEvent::STATUS_PROCESSED, 'processed_at' => now()]);

        // 2nd call (replay)
        $response = $this->call('POST', '/webhooks/stripe-connect', [], [], [],
            ['HTTP_Stripe-Signature' => $signature], $payload);

        $response->assertOk();
        $response->assertJson(['dispatched' => false]);

        $this->assertSame(1, StripeWebhookEvent::where('stripe_event_id', 'evt_test_replay')->count());
        Queue::assertPushed(ProcessStripeWebhookJob::class, 1);
    }

    protected function makeStripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
