<?php

namespace Tests\Feature\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8Test extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────
    // PushSubscription model
    // ──────────────────────────────────────────────────────

    public function test_endpoint_hash_is_consistent(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';
        $hash1 = PushSubscription::hashEndpoint($endpoint);
        $hash2 = PushSubscription::hashEndpoint($endpoint);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1)); // sha256 hex = 64 chars
    }

    public function test_record_failure_increments_count(): void
    {
        $user = User::factory()->create();
        $sub = PushSubscription::create([
            'user_id'       => $user->id,
            'endpoint'      => 'https://example.com/sub1',
            'endpoint_hash' => PushSubscription::hashEndpoint('https://example.com/sub1'),
            'p256dh'        => 'fake-key',
            'auth'          => 'fake-auth',
            'is_active'     => true,
            'failure_count' => 0,
        ]);

        $sub->recordFailure();
        $this->assertSame(1, $sub->fresh()->failure_count);
        $this->assertNotNull($sub->fresh()->last_failure_at);
        $this->assertTrue($sub->fresh()->is_active); // pas encore désactivé
    }

    public function test_record_failure_disables_after_5_failures(): void
    {
        $user = User::factory()->create();
        $sub = PushSubscription::create([
            'user_id'       => $user->id,
            'endpoint'      => 'https://example.com/sub2',
            'endpoint_hash' => PushSubscription::hashEndpoint('https://example.com/sub2'),
            'p256dh'        => 'k', 'auth' => 'a',
            'is_active'     => true,
            'failure_count' => 4,
        ]);

        $sub->recordFailure();

        $this->assertSame(5, $sub->fresh()->failure_count);
        $this->assertFalse($sub->fresh()->is_active);
    }

    public function test_record_success_resets_failure_count(): void
    {
        $user = User::factory()->create();
        $sub = PushSubscription::create([
            'user_id'       => $user->id,
            'endpoint'      => 'https://example.com/sub3',
            'endpoint_hash' => PushSubscription::hashEndpoint('https://example.com/sub3'),
            'p256dh'        => 'k', 'auth' => 'a',
            'is_active'     => true,
            'failure_count' => 3,
        ]);

        $sub->recordSuccess();

        $this->assertSame(0, $sub->fresh()->failure_count);
        $this->assertNotNull($sub->fresh()->last_used_at);
    }

    public function test_active_scope_filters_correctly(): void
    {
        $user = User::factory()->create();

        PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => 'a',
            'endpoint_hash' => PushSubscription::hashEndpoint('a'),
            'p256dh' => 'k', 'auth' => 'a', 'is_active' => true,
        ]);
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint' => 'b',
            'endpoint_hash' => PushSubscription::hashEndpoint('b'),
            'p256dh' => 'k', 'auth' => 'a', 'is_active' => false,
        ]);

        $this->assertSame(1, PushSubscription::active()->count());
        $this->assertSame(2, PushSubscription::forUser($user->id)->count());
    }

    public function test_to_web_push_array_format(): void
    {
        $user = User::factory()->create();
        $sub = PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://example.com/x',
            'endpoint_hash' => PushSubscription::hashEndpoint('https://example.com/x'),
            'p256dh' => 'public-key-here',
            'auth' => 'auth-secret-here',
            'is_active' => true,
        ]);

        $arr = $sub->toWebPushArray();

        $this->assertSame('https://example.com/x', $arr['endpoint']);
        $this->assertSame('public-key-here', $arr['publicKey']);
        $this->assertSame('auth-secret-here', $arr['authToken']);
    }

    // ──────────────────────────────────────────────────────
    // PushSubscriptionController
    // ──────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_subscribe(): void
    {
        $response = $this->postJson('/push/subscribe', []);
        $response->assertStatus(401);
    }

    public function test_subscribe_creates_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
                'auth'   => 'tBHItJI5svbpez7KI4CCXg',
            ],
            'platform' => 'desktop',
            'browser'  => 'chrome',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id'  => $user->id,
            'platform' => 'desktop',
            'browser'  => 'chrome',
            'is_active' => true,
        ]);
    }

    public function test_subscribe_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => 'not-a-url',
        ]);

        $response->assertStatus(422);
    }

    public function test_subscribe_updates_existing_subscription(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/same-endpoint';

        // Première subscribe
        $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'key1', 'auth' => 'auth1'],
        ]);

        // Re-subscribe avec nouvelle clé
        $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'key2-updated', 'auth' => 'auth2-updated'],
        ]);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'p256dh'  => 'key2-updated',
        ]);
    }

    public function test_unsubscribe_disables_subscription(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/to-unsub';

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'k', 'auth' => 'a',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/push/unsubscribe', [
            'endpoint' => $endpoint,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id'  => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_public_key_endpoint_returns_config(): void
    {
        config(['services.webpush.public_key' => 'TEST_PUBLIC_KEY_VALUE']);

        $response = $this->getJson('/push/public-key');
        $response->assertOk();
        $response->assertJson(['public_key' => 'TEST_PUBLIC_KEY_VALUE']);
    }

    public function test_user_cannot_unsubscribe_other_user_subscription(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/user1-only';

        PushSubscription::create([
            'user_id' => $user1->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'p256dh' => 'k', 'auth' => 'a',
            'is_active' => true,
        ]);

        // user2 essaie de désinscrire l'endpoint de user1
        $this->actingAs($user2)->postJson('/push/unsubscribe', [
            'endpoint' => $endpoint,
        ]);

        // Sub de user1 toujours active
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id'   => $user1->id,
            'is_active' => true,
        ]);
    }
}
