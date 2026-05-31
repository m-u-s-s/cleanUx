<?php

namespace Tests\Feature\Spine;

use App\Models\ProviderPayout;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\StripeConnectPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class CaptureGuardsTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashier.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    private function authorizedScenario(string $piId): SpineScenario
    {
        $s = SpineScenario::make()->build();
        $this->stripe->stub('POST', '/v1/payment_intents', StripeFakeResponses::paymentIntent($piId, 'requires_capture'));
        app(MissionPaymentService::class)->authorize($s->booking, 'pm_card_visa');
        $s->booking->refresh();

        return $s;
    }

    public function test_f1_double_capture_is_a_noop_second_time(): void
    {
        $s = $this->authorizedScenario('pi_f1');
        $this->stripe->stub('POST', '/v1/payment_intents/pi_f1/capture', StripeFakeResponses::paymentIntent('pi_f1', 'succeeded'));

        $svc = app(StripeConnectPaymentService::class);
        $first = $svc->captureMissionPayment($s->mission->fresh());
        $this->assertNotNull($first);

        $second = $svc->captureMissionPayment($s->mission->fresh());
        $this->assertNull($second, 'second capture must be a no-op (F1)');
        $this->assertSame(1, ProviderPayout::where('provider_user_id', $s->provider->id)->count());
    }

    public function test_f7_declined_at_capture_marks_failed_and_throws(): void
    {
        $s = $this->authorizedScenario('pi_f7');
        $this->stripe->stub('POST', '/v1/payment_intents/pi_f7/capture', [
            'error' => ['type' => 'card_error', 'code' => 'card_declined', 'message' => 'Your card was declined.'],
        ], 402);

        $svc = app(StripeConnectPaymentService::class);
        try {
            $svc->captureMissionPayment($s->mission->fresh());
            $this->fail('capture should throw on decline (F7)');
        } catch (RuntimeException $e) {
            // expected
        }
        $s->booking->refresh();
        $this->assertSame('failed', $s->booking->payment_status, 'declined capture must mark booking failed (F7)');
        $this->assertSame(0, ProviderPayout::where('provider_user_id', $s->provider->id)->count());
    }

    public function test_f6_unauthorized_booking_cannot_be_captured(): void
    {
        $s = SpineScenario::make()->build(); // booking stays payment_status='pending'
        $svc = app(StripeConnectPaymentService::class);
        $result = $svc->captureMissionPayment($s->mission->fresh());
        $this->assertNull($result, 'capture must no-op when booking is not authorized (F6)');
    }
}
