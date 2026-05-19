<?php

namespace Tests\Feature\WebhooksV2;

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebhookApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('webhooks_v2.enabled', true);
        Config::set('webhooks_v2.allowed_events', [
            'booking.created', 'booking.cancelled', 'payment.succeeded', 'test.ping',
        ]);
        Bus::fake();
    }

    public function test_admin_create_endpoint_persists_with_subscriptions(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/webhooks-v2/endpoints', [
            'name' => 'Acme prod',
            'url' => 'https://acme.example.com/webhooks',
            'event_codes' => ['booking.created', 'payment.succeeded'],
        ]);

        $response->assertCreated();
        $this->assertSame(1, WebhookEndpoint::count());
        $this->assertSame(2, WebhookSubscription::count());
        $endpoint = $response->json('endpoint');
        $this->assertNotEmpty($endpoint['secret'] ?? null);
        $this->assertStringStartsWith('whsec_', $endpoint['secret']);
    }

    public function test_admin_create_endpoint_rejects_unknown_event_code(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/webhooks-v2/endpoints', [
            'name' => 'Bad', 'url' => 'https://bad.test/h',
            'event_codes' => ['booking.created', 'secret.leak'],
        ]);
        $response->assertStatus(422);
    }

    public function test_admin_rotate_secret_returns_new_value(): void
    {
        $admin = User::factory()->admin()->create();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_r', 'name' => 'R', 'url' => 'https://r.test',
            'secret' => 'whsec_original', 'is_active' => true,
        ]);
        $original = $ep->secret;

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/webhooks-v2/endpoints/{$ep->id}/rotate-secret");
        $response->assertOk();
        $this->assertNotSame($original, $response->json('endpoint.secret'));
    }

    public function test_admin_test_endpoint_emits_test_event_and_creates_delivery(): void
    {
        $admin = User::factory()->admin()->create();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_test', 'name' => 'Test', 'url' => 'https://test.test/hook',
            'secret' => 'whsec_test', 'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/webhooks-v2/endpoints/{$ep->id}/test");
        $response->assertOk();
        $this->assertSame('test.ping', $response->json('event.event_code'));
        $this->assertSame(1, WebhookDelivery::query()->where('endpoint_id', $ep->id)->count());
    }

    public function test_admin_replay_delivery_resets_status(): void
    {
        $admin = User::factory()->admin()->create();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_rp', 'name' => 'RP', 'url' => 'https://rp.test',
            'secret' => 'whsec_rp', 'is_active' => true,
        ]);
        $event = WebhookEvent::query()->create([
            'event_id' => 'evt_rp', 'event_code' => 'booking.created',
            'payload' => [], 'occurred_at' => now(),
        ]);
        $delivery = WebhookDelivery::query()->create([
            'event_id' => $event->id, 'endpoint_id' => $ep->id,
            'status' => WebhookDelivery::STATUS_FAILED, 'attempt' => 2, 'max_attempts' => 6,
            'last_error' => 'timeout',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/webhooks-v2/deliveries/{$delivery->id}/replay");
        $response->assertOk();
        $this->assertSame(WebhookDelivery::STATUS_PENDING, $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->last_error);
    }

    public function test_admin_list_endpoints_returns_data(): void
    {
        $admin = User::factory()->admin()->create();
        WebhookEndpoint::query()->create([
            'code' => 'whe_l1', 'name' => 'L1', 'url' => 'https://l1.test',
            'secret' => 'whsec_l1', 'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/webhooks-v2/endpoints');
        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_admin_update_endpoint_toggles_active(): void
    {
        $admin = User::factory()->admin()->create();
        $ep = WebhookEndpoint::query()->create([
            'code' => 'whe_u', 'name' => 'U', 'url' => 'https://u.test',
            'secret' => 'whsec_u', 'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/admin/webhooks-v2/endpoints/{$ep->id}", [
            'is_active' => false,
        ]);
        $response->assertOk();
        $this->assertFalse((bool) $ep->fresh()->is_active);
    }

    public function test_unauthenticated_admin_endpoints_blocked(): void
    {
        $this->postJson('/api/admin/webhooks-v2/endpoints', [
            'name' => 'X', 'url' => 'https://x.test', 'event_codes' => ['booking.created'],
        ])->assertStatus(401);
    }
}
