<?php

namespace Tests\Feature\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionControllerCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_creates_active_subscription_for_user(): void
    {
        $user = User::factory()->client()->create();

        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc-123';

        $response = $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'public-key-value',
                'auth' => 'auth-token-value',
            ],
            'platform' => 'web',
            'browser' => 'Chrome',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'is_active' => true,
            'failure_count' => 0,
        ]);

        $this->assertSame(
            PushSubscription::query()->where('user_id', $user->id)->value('id'),
            $response->json('subscription_id')
        );
    }

    public function test_subscribe_reactivates_existing_subscription_without_duplicating(): void
    {
        $user = User::factory()->client()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/reuse-me';

        $existing = PushSubscription::factory()->inactive()->create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
        ]);

        $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'fresh-public',
                'auth' => 'fresh-auth',
            ],
        ])->assertOk();

        $this->assertSame(1, PushSubscription::query()->where('user_id', $user->id)->count());

        $existing->refresh();
        $this->assertTrue($existing->is_active);
        $this->assertSame(0, $existing->failure_count);
        $this->assertSame('fresh-public', $existing->p256dh);
    }

    public function test_subscribe_rejects_invalid_payload(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)->postJson('/push/subscribe', [
            'endpoint' => 'not-a-url',
            'keys' => [],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth', 'endpoint']);
    }

    public function test_subscribe_requires_authentication(): void
    {
        $this->postJson('/push/subscribe', [
            'endpoint' => 'https://example.com/ep',
            'keys' => ['p256dh' => 'a', 'auth' => 'b'],
        ])->assertStatus(401);
    }

    public function test_unsubscribe_deactivates_matching_subscription(): void
    {
        $user = User::factory()->client()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/kill-me';

        $sub = PushSubscription::factory()->create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'is_active' => true,
        ]);

        $this->actingAs($user)->postJson('/push/unsubscribe', [
            'endpoint' => $endpoint,
        ])->assertOk()->assertJson(['ok' => true]);

        $sub->refresh();
        $this->assertFalse($sub->is_active);
    }

    public function test_unsubscribe_returns_422_when_endpoint_missing(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)->postJson('/push/unsubscribe', [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'error' => 'endpoint required']);
    }

    public function test_public_key_returns_configured_value(): void
    {
        config(['services.webpush.public_key' => 'VAPID-PUB-XYZ']);

        $this->getJson('/push/public-key')
            ->assertOk()
            ->assertJson(['public_key' => 'VAPID-PUB-XYZ']);
    }

    public function test_test_endpoint_returns_result_summary_when_no_subscriptions(): void
    {
        $user = User::factory()->client()->create();

        $response = $this->actingAs($user)->postJson('/push/test');

        $response->assertOk()->assertJson([
            'ok' => true,
            'result' => ['sent' => 0, 'failed' => 0, 'deactivated' => 0],
        ]);
    }
}
