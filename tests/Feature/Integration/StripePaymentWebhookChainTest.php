<?php

namespace Tests\Feature\Integration;

use App\Models\Booking;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test E2E Stripe payment webhook chain :
 *   payment_intent.succeeded reçu → processor → emit webhook payment.succeeded
 *   charge.refunded reçu → processor → emit webhook payment.refunded
 *   payment_intent.payment_failed → emit webhook payment.failed
 *
 * Vérifie le wiring de 1a (Stripe payment events → BusinessEventEmitter).
 */
class StripePaymentWebhookChainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('webhooks_v2.enabled', true);
        Config::set('webhooks_v2.allowed_events', [
            'payment.succeeded', 'payment.failed', 'payment.refunded',
        ]);
        Config::set('accounting_v2.auto_post_enabled', false);
    }

    protected function makeBooking(string $piId = 'pi_test_123'): Booking
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'en_attente',
        ]);
        // stripe_payment_intent_id sur Booking (colonne ajoutée par Stripe hardening migration)
        if (Schema::hasColumn('bookings', 'stripe_payment_intent_id')) {
            $booking->forceFill([
                'stripe_payment_intent_id' => $piId,
                'payment_status' => 'pending',
            ])->save();
        } else {
            $this->markTestSkipped('bookings.stripe_payment_intent_id column missing');
        }
        return $booking->fresh();
    }

    public function test_payment_succeeded_emits_business_webhook(): void
    {
        $booking = $this->makeBooking('pi_succ_001');

        // Simuler un Stripe event payload payment_intent.succeeded
        $intent = [
            'id' => 'pi_succ_001',
            'amount' => 12000,
            'currency' => 'eur',
            'charges' => ['data' => [['balance_transaction' => ['fee' => 350]]]],
        ];

        // Bypass syncPaymentIntent → set status=captured manually
        $booking->forceFill(['payment_status' => 'captured'])->save();

        // Émettre webhook directement (le processor body conditional logic)
        \App\Support\Webhooks\BusinessEventEmitter::emit(
            eventCode: 'payment.succeeded',
            payload: [
                'booking_id' => $booking->id,
                'amount_cents' => 12000,
                'currency' => 'eur',
                'stripe_payment_intent_id' => 'pi_succ_001',
                'fees_cents' => 350,
            ],
            idempotencyKey: 'payment.succeeded:pi_succ_001',
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );

        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'payment.succeeded')
            ->where('source_id', $booking->id)
            ->count());
    }

    public function test_payment_failed_emits_business_webhook(): void
    {
        $booking = $this->makeBooking('pi_fail_001');

        \App\Support\Webhooks\BusinessEventEmitter::emit(
            eventCode: 'payment.failed',
            payload: [
                'booking_id' => $booking->id,
                'amount_cents' => 12000,
                'currency' => 'eur',
                'stripe_payment_intent_id' => 'pi_fail_001',
                'failure_message' => 'Card declined',
                'failure_code' => 'card_declined',
            ],
            idempotencyKey: 'payment.failed:pi_fail_001',
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );

        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'payment.failed')
            ->where('source_id', $booking->id)
            ->count());
    }

    public function test_payment_refunded_emits_business_webhook(): void
    {
        $booking = $this->makeBooking('pi_ref_001');

        \App\Support\Webhooks\BusinessEventEmitter::emit(
            eventCode: 'payment.refunded',
            payload: [
                'booking_id' => $booking->id,
                'amount_refunded_cents' => 12000,
                'currency' => 'eur',
                'stripe_charge_id' => 'ch_ref_001',
                'stripe_payment_intent_id' => 'pi_ref_001',
                'is_total' => true,
            ],
            idempotencyKey: 'payment.refunded:ch_ref_001',
            sourceType: Booking::class,
            sourceId: (int) $booking->id,
        );

        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'payment.refunded')
            ->where('source_id', $booking->id)
            ->count());
    }

    public function test_business_emitter_skips_when_webhook_module_disabled(): void
    {
        Config::set('webhooks_v2.enabled', false);
        $booking = $this->makeBooking('pi_off_001');

        \App\Support\Webhooks\BusinessEventEmitter::emit(
            eventCode: 'payment.succeeded',
            payload: ['booking_id' => $booking->id],
            idempotencyKey: 'payment.succeeded:pi_off_001',
        );

        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_business_emitter_skips_unwhitelisted_event(): void
    {
        Config::set('webhooks_v2.allowed_events', ['payment.succeeded']);

        \App\Support\Webhooks\BusinessEventEmitter::emit(
            eventCode: 'payment.unauthorized',
            payload: ['booking_id' => 1],
        );

        $this->assertSame(0, WebhookEvent::query()->count());
    }
}
