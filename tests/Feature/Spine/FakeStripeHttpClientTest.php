<?php

namespace Tests\Feature\Spine;

use Stripe\ApiRequestor;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class FakeStripeHttpClientTest extends TestCase
{
    public function test_fake_intercepts_payment_intent_create_and_capture(): void
    {
        Stripe::setApiKey('sk_test_fake');
        $fake = new FakeStripeHttpClient;
        $fake->stub('POST', '/v1/payment_intents', StripeFakeResponses::paymentIntent('pi_test_1', 'requires_capture'));
        $fake->stub('POST', '/v1/payment_intents/pi_test_1/capture', StripeFakeResponses::paymentIntent('pi_test_1', 'succeeded'));
        ApiRequestor::setHttpClient($fake);

        $pi = PaymentIntent::create(['amount' => 1000, 'currency' => 'eur', 'capture_method' => 'manual']);
        $this->assertSame('pi_test_1', $pi->id);
        $this->assertSame('requires_capture', $pi->status);

        $captured = PaymentIntent::retrieve('pi_test_1'); // GET falls back to last-known
        $captured->capture();
        $this->assertSame('succeeded', $captured->status);

        $this->assertContains('POST /v1/payment_intents', $fake->calls());
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }
}
