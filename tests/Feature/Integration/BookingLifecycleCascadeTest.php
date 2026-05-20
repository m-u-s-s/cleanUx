<?php

namespace Tests\Feature\Integration;

use App\Models\Booking;
use App\Models\ChatThread;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Test d'intégration cross-modules : valide la cascade BookingObserver
 * après les chantiers A1+A3+A8.
 *
 * Quand un Booking est créé puis complété :
 *  - BookingObserver::created → trackAnalytics + emit booking.created + ChatService::startThread
 *  - BookingObserver::saved (status=completed) → emit booking.completed + BookingAutoPoster::postSale + Chat archive
 */
class BookingLifecycleCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        // Webhooks v2 actif avec endpoint subscribed
        Config::set('webhooks_v2.enabled', true);
        Config::set('webhooks_v2.allowed_events', [
            'booking.created', 'booking.completed', 'booking.cancelled', 'booking.scheduled',
        ]);

        // Chat v2 actif
        Config::set('chat_v2.enabled', true);
        Config::set('chat_v2.allowed_context_types', ['booking', 'dispute', 'admin', 'generic']);
        Config::set('chat_v2.broadcast_enabled', false);
        Config::set('chat_v2.auto_close_on_booking_completed', true);

        // Accounting auto-post DÉSACTIVÉ par défaut — on teste le chemin standard A3 sans toucher la compta
        Config::set('accounting_v2.auto_post_enabled', false);
    }

    public function test_booking_created_triggers_webhook_and_chat_thread(): void
    {
        $endpoint = WebhookEndpoint::query()->create([
            'code' => 'whe_lcc', 'name' => 'LCC', 'url' => 'https://lcc.test',
            'secret' => 'whsec_lcc', 'is_active' => true, 'max_attempts' => 3,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $endpoint->id, 'event_code' => 'booking.created', 'is_active' => true,
        ]);

        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'en_attente',
        ]);

        // Webhook event booking.created persisté
        $this->assertSame(1, WebhookEvent::query()->where('event_code', 'booking.created')->count());
        $event = WebhookEvent::query()->where('event_code', 'booking.created')->first();
        $this->assertSame($booking->id, (int) $event->payload['booking_id']);
        $this->assertSame($client->id, (int) $event->payload['client_id']);
        $this->assertSame($provider->id, (int) $event->payload['provider_id']);
        // Delivery créée pour endpoint
        $this->assertSame(1, WebhookDelivery::query()
            ->where('endpoint_id', $endpoint->id)
            ->where('event_id', $event->id)
            ->count());

        // Chat thread auto-créé pour ce booking
        $thread = ChatThread::query()
            ->forContext('booking', (int) $booking->id)
            ->first();
        $this->assertNotNull($thread);
        $this->assertSame(2, $thread->participants()->count());
    }

    public function test_booking_completed_emits_webhook_and_archives_chat(): void
    {
        $endpoint = WebhookEndpoint::query()->create([
            'code' => 'whe_cmp', 'name' => 'Complete', 'url' => 'https://cmp.test',
            'secret' => 'whsec_cmp', 'is_active' => true,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $endpoint->id, 'event_code' => 'booking.completed', 'is_active' => true,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $endpoint->id, 'event_code' => 'booking.created', 'is_active' => true,
        ]);

        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'en_cours',
        ]);

        // Transition à terminé
        $booking->update(['status' => 'termine']);

        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'booking.completed')
            ->where('source_id', $booking->id)
            ->count());

        // Chat thread doit être archivé
        $thread = ChatThread::query()
            ->forContext('booking', (int) $booking->id)
            ->first();
        $this->assertNotNull($thread);
        $this->assertTrue((bool) $thread->fresh()->is_archived);
    }

    public function test_booking_cancelled_emits_cancelled_webhook(): void
    {
        $endpoint = WebhookEndpoint::query()->create([
            'code' => 'whe_can', 'name' => 'Cancel', 'url' => 'https://can.test',
            'secret' => 'whsec_can', 'is_active' => true,
        ]);
        WebhookSubscription::query()->create([
            'endpoint_id' => $endpoint->id, 'event_code' => 'booking.cancelled', 'is_active' => true,
        ]);

        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $booking->update(['status' => 'annule']);

        $this->assertSame(1, WebhookEvent::query()
            ->where('event_code', 'booking.cancelled')
            ->where('source_id', $booking->id)
            ->count());
    }

    public function test_chat_module_disabled_does_not_break_booking_create(): void
    {
        Config::set('chat_v2.enabled', false);

        $booking = Booking::factory()->create(['status' => 'en_attente']);

        $this->assertNotNull($booking->id);
        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_webhook_module_disabled_does_not_break_booking_create(): void
    {
        Config::set('webhooks_v2.enabled', false);

        $booking = Booking::factory()->create(['status' => 'en_attente']);

        $this->assertNotNull($booking->id);
        $this->assertSame(0, WebhookEvent::query()->count());
    }
}
