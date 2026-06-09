<?php

namespace Tests\Feature\WebhooksV2;

use App\Jobs\WebhooksV2\DeliverWebhookJob;
use App\Livewire\Admin\WebhooksV2\WebhooksCenter;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class AdminWebhooksCenterComponentTest extends TestCase
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

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    protected function makeEndpoint(array $overrides = []): WebhookEndpoint
    {
        return WebhookEndpoint::query()->create(array_merge([
            'code' => WebhookEndpoint::generateCode(),
            'name' => 'EP',
            'url' => 'https://example.test/hook',
            'secret' => WebhookEndpoint::generateSecret(),
            'is_active' => true,
            'max_attempts' => 6,
            'timeout_seconds' => 15,
        ], $overrides));
    }

    public function test_render_endpoints_tab_with_kpis(): void
    {
        $this->actingAs($this->createAdmin());
        $this->makeEndpoint(['name' => 'Active EP']);
        $this->makeEndpoint(['name' => 'Suspended EP', 'is_suspended' => true]);

        Livewire::test(WebhooksCenter::class)
            ->assertOk()
            ->assertSet('tab', 'endpoints')
            ->assertSee('Active EP');
    }

    public function test_render_events_tab(): void
    {
        $this->actingAs($this->createAdmin());
        WebhookEvent::query()->create([
            'event_id' => 'evt_x1', 'event_code' => 'booking.created',
            'payload' => [], 'occurred_at' => now(),
        ]);

        Livewire::test(WebhooksCenter::class)
            ->set('tab', 'events')
            ->assertOk()
            ->assertSee('booking.created');
    }

    public function test_render_deliveries_tab_with_filters(): void
    {
        $this->actingAs($this->createAdmin());
        $ep = $this->makeEndpoint();
        $event = WebhookEvent::query()->create([
            'event_id' => 'evt_d1', 'event_code' => 'booking.created',
            'payload' => [], 'occurred_at' => now(),
        ]);
        WebhookDelivery::query()->create([
            'event_id' => $event->id, 'endpoint_id' => $ep->id,
            'status' => WebhookDelivery::STATUS_DEAD, 'attempt' => 6, 'max_attempts' => 6,
        ]);

        Livewire::test(WebhooksCenter::class)
            ->set('tab', 'deliveries')
            ->set('filterStatus', WebhookDelivery::STATUS_DEAD)
            ->set('filterEndpointId', $ep->id)
            ->assertOk();
    }

    public function test_create_endpoint_persists_endpoint_and_subscriptions(): void
    {
        $this->actingAs($this->createAdmin());

        Livewire::test(WebhooksCenter::class)
            ->set('newName', 'My Integration')
            ->set('newUrl', 'https://hooks.example.test/in')
            ->set('newEventCodes', ['booking.created', 'booking.cancelled'])
            ->call('createEndpoint')
            ->assertHasNoErrors()
            ->assertSet('newName', '')
            ->assertDispatched('toast');

        $ep = WebhookEndpoint::query()->where('name', 'My Integration')->first();
        $this->assertNotNull($ep);
        $this->assertSame(2, WebhookSubscription::query()->where('endpoint_id', $ep->id)->count());
    }

    public function test_create_endpoint_validation_error_emits_toast(): void
    {
        $this->actingAs($this->createAdmin());

        Livewire::test(WebhooksCenter::class)
            ->set('newName', '')
            ->set('newUrl', 'not-a-url')
            ->set('newEventCodes', [])
            ->call('createEndpoint')
            ->assertDispatched('toast');

        $this->assertSame(0, WebhookEndpoint::query()->count());
    }

    public function test_rotate_secret_changes_secret(): void
    {
        $this->actingAs($this->createAdmin());
        $ep = $this->makeEndpoint(['secret' => 'whsec_original']);

        Livewire::test(WebhooksCenter::class)
            ->call('rotateSecret', $ep->id)
            ->assertDispatched('toast');

        $this->assertNotSame('whsec_original', $ep->fresh()->secret);
    }

    public function test_toggle_suspend_flips_state(): void
    {
        $this->actingAs($this->createAdmin());
        $ep = $this->makeEndpoint(['is_suspended' => false, 'consecutive_failures' => 5]);

        Livewire::test(WebhooksCenter::class)
            ->call('toggleSuspend', $ep->id)
            ->assertDispatched('toast');

        $ep->refresh();
        $this->assertTrue($ep->is_suspended);
        $this->assertSame('Suspended via admin UI', $ep->suspension_reason);

        Livewire::test(WebhooksCenter::class)
            ->call('toggleSuspend', $ep->id);

        $ep->refresh();
        $this->assertFalse($ep->is_suspended);
        $this->assertNull($ep->suspension_reason);
        $this->assertSame(0, $ep->consecutive_failures);
    }

    public function test_send_test_creates_delivery_and_dispatches_job(): void
    {
        Bus::fake();
        $this->actingAs($this->createAdmin());
        $ep = $this->makeEndpoint();

        Livewire::test(WebhooksCenter::class)
            ->call('sendTest', $ep->id)
            ->assertDispatched('toast');

        $this->assertTrue(
            WebhookDelivery::query()->where('endpoint_id', $ep->id)->exists()
        );
        Bus::assertDispatched(DeliverWebhookJob::class);
    }

    public function test_replay_resets_delivery(): void
    {
        Bus::fake();
        $this->actingAs($this->createAdmin());
        $ep = $this->makeEndpoint();
        $event = WebhookEvent::query()->create([
            'event_id' => 'evt_r1', 'event_code' => 'booking.created',
            'payload' => [], 'occurred_at' => now(),
        ]);
        $delivery = WebhookDelivery::query()->create([
            'event_id' => $event->id, 'endpoint_id' => $ep->id,
            'status' => WebhookDelivery::STATUS_FAILED, 'attempt' => 3, 'max_attempts' => 6,
            'last_error' => 'timeout',
        ]);

        Livewire::test(WebhooksCenter::class)
            ->call('replay', $delivery->id)
            ->assertDispatched('toast');

        $this->assertSame(WebhookDelivery::STATUS_PENDING, $delivery->fresh()->status);
        Bus::assertDispatched(DeliverWebhookJob::class);
    }
}
