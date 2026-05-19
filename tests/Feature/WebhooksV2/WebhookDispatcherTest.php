<?php

namespace Tests\Feature\WebhooksV2;

use App\Jobs\WebhooksV2\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use App\Services\WebhooksV2\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('webhooks_v2.enabled', true);
        Config::set('webhooks_v2.allowed_events', [
            'booking.created', 'booking.cancelled', 'test.ping',
        ]);
    }

    public function test_emit_persists_event_and_creates_deliveries_for_active_subscriptions(): void
    {
        Bus::fake();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_t1', 'name' => 'EP1', 'url' => 'https://a.test/hook',
            'secret' => 'whsec_x', 'is_active' => true, 'max_attempts' => 5,
            'timeout_seconds' => 10,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $ep->id, 'event_code' => 'booking.created', 'is_active' => true,
        ]);

        $event = app(WebhookDispatcher::class)->emit('booking.created', ['booking_id' => 42]);

        $this->assertNotNull($event);
        $this->assertSame('booking.created', $event->event_code);
        $this->assertSame(1, WebhookDelivery::query()->where('event_id', $event->id)->count());
        Bus::assertDispatched(DeliverWebhookJob::class);
    }

    public function test_emit_ignored_when_event_not_whitelisted(): void
    {
        Bus::fake();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_t2', 'name' => 'EP2', 'url' => 'https://a.test/hook',
            'secret' => 'whsec_y', 'is_active' => true,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $ep->id, 'event_code' => 'forbidden.event', 'is_active' => true,
        ]);

        $event = app(WebhookDispatcher::class)->emit('forbidden.event', []);

        $this->assertNull($event);
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_emit_idempotency_returns_existing_event(): void
    {
        Bus::fake();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_t3', 'name' => 'EP3', 'url' => 'https://a.test/hook',
            'secret' => 'whsec_z', 'is_active' => true,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $ep->id, 'event_code' => 'booking.created', 'is_active' => true,
        ]);

        $a = app(WebhookDispatcher::class)->emit('booking.created', ['x' => 1], idempotencyKey: 'abc-123');
        $b = app(WebhookDispatcher::class)->emit('booking.created', ['x' => 2], idempotencyKey: 'abc-123');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, WebhookEvent::count());
    }

    public function test_emit_skips_inactive_or_suspended_endpoints(): void
    {
        Bus::fake();
        $active = WebhookEndpoint::query()->create([
            'code' => 'whe_a', 'name' => 'A', 'url' => 'https://a.test',
            'secret' => 'whsec_a', 'is_active' => true,
        ]);
        $suspended = WebhookEndpoint::query()->create([
            'code' => 'whe_s', 'name' => 'S', 'url' => 'https://s.test',
            'secret' => 'whsec_s', 'is_active' => true, 'is_suspended' => true,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $active->id, 'event_code' => 'booking.created', 'is_active' => true,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $suspended->id, 'event_code' => 'booking.created', 'is_active' => true,
        ]);

        $event = app(WebhookDispatcher::class)->emit('booking.created', []);

        $this->assertSame(1, WebhookDelivery::query()->where('event_id', $event->id)->count());
        $this->assertTrue(
            WebhookDelivery::query()->where('endpoint_id', $active->id)->exists()
        );
    }

    public function test_emit_respects_subscription_filters(): void
    {
        Bus::fake();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_f', 'name' => 'F', 'url' => 'https://f.test',
            'secret' => 'whsec_f', 'is_active' => true,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $ep->id,
            'event_code' => 'booking.created',
            'filters' => ['trade_code' => 'cleaning'],
            'is_active' => true,
        ]);

        $eventMatching = app(WebhookDispatcher::class)->emit('booking.created', ['trade_code' => 'cleaning']);
        $eventNotMatching = app(WebhookDispatcher::class)->emit('booking.created', ['trade_code' => 'painting']);

        $this->assertSame(1, WebhookDelivery::query()->where('event_id', $eventMatching->id)->count());
        $this->assertSame(0, WebhookDelivery::query()->where('event_id', $eventNotMatching->id)->count());
    }

    public function test_replay_resets_delivery_and_redispatches(): void
    {
        Bus::fake();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_r', 'name' => 'R', 'url' => 'https://r.test',
            'secret' => 'whsec_r', 'is_active' => true,
        ]);
        $event = WebhookEvent::query()->create([
            'event_id' => 'evt_r1', 'event_code' => 'booking.created',
            'payload' => [], 'occurred_at' => now(),
        ]);
        $delivery = WebhookDelivery::query()->create([
            'event_id' => $event->id, 'endpoint_id' => $ep->id,
            'status' => WebhookDelivery::STATUS_FAILED, 'attempt' => 3, 'max_attempts' => 6,
            'last_error' => 'timeout', 'next_retry_at' => now()->addHours(2),
        ]);

        $replayed = app(WebhookDispatcher::class)->replay($delivery);

        $this->assertSame(WebhookDelivery::STATUS_PENDING, $replayed->status);
        $this->assertNull($replayed->next_retry_at);
        $this->assertNull($replayed->last_error);
        Bus::assertDispatched(DeliverWebhookJob::class);
    }
}
