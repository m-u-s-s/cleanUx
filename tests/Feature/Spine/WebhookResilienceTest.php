<?php

namespace Tests\Feature\Spine;

use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class WebhookResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure no Stripe API key is set so syncPaymentIntent fails-fast with
        // AuthenticationException (no network call). The try/catch in
        // StripeConnectPaymentService::syncPaymentIntent catches \Throwable and
        // returns early, leaving the booking's payment_status unchanged.
        // This means a pre-set 'captured' booking stays 'captured' and
        // recordEarning is invoked as expected.
        config(['cashier.secret' => null, 'services.stripe.secret' => null]);
    }

    private function capturedScenario(string $piId): SpineScenario
    {
        $s = SpineScenario::make()->build();
        $s->booking->forceFill([
            'stripe_payment_intent_id' => $piId,
            'payment_status' => 'captured',
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
        ])->save();

        return $s;
    }

    private function succeededEvent(string $piId, string $eventId): StripeWebhookEvent
    {
        return StripeWebhookEvent::create([
            'stripe_event_id' => $eventId,
            'type' => 'payment_intent.succeeded',
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'received_at' => now(),
            'payload' => ['data' => ['object' => StripeFakeResponses::paymentIntent($piId, 'succeeded')]],
        ]);
    }

    public function test_f2_replayed_succeeded_webhook_credits_wallet_once(): void
    {
        $s = $this->capturedScenario('pi_f2');
        $processor = app(StripeWebhookEventProcessor::class);

        // Two DISTINCT events for the same PI (Stripe sometimes resends as a new event id).
        $processor->process($this->succeededEvent('pi_f2', 'evt_a'));
        $processor->process($this->succeededEvent('pi_f2', 'evt_b'));

        $count = ProviderWalletTransaction::where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->count();
        $this->assertSame(1, $count, 'replayed succeeded webhook must credit wallet exactly once (F2)');
    }

    public function test_f2_same_event_processed_twice_is_idempotent(): void
    {
        $s = $this->capturedScenario('pi_f2b');
        $event = $this->succeededEvent('pi_f2b', 'evt_same');

        $processor = app(StripeWebhookEventProcessor::class);
        $processor->process($event);
        $processor->process($event->fresh()); // already terminal → short-circuits

        $count = ProviderWalletTransaction::where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->count();
        $this->assertSame(1, $count, 'reprocessing the same terminal event must not double-credit (F2)');
    }
}
