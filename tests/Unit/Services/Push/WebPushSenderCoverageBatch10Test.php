<?php

namespace Tests\Unit\Services\Push;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Push\WebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Coverage for WebPushSender — exercises the early-return branches
 * (no subscriptions, missing VAPID config) plus the protected
 * normalizePayload/vapidConfig helpers via reflection. The live flush()
 * path requires real network + valid EC keys, so it is intentionally
 * left uncovered here.
 */
class WebPushSenderCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    private WebPushSender $sender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sender = new WebPushSender;

        // Ensure VAPID is treated as unconfigured for the send-path tests.
        config([
            'services.webpush.public_key' => null,
            'services.webpush.private_key' => null,
        ]);
    }

    public function test_send_to_user_with_no_subscriptions_returns_zeros(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->sender->sendToUser($user, ['title' => 'Hello']);

        $this->assertSame(['sent' => 0, 'failed' => 0, 'deactivated' => 0], $result);
    }

    public function test_send_to_user_ignores_inactive_subscriptions(): void
    {
        $user = User::factory()->client()->create();
        PushSubscription::factory()->inactive()->create(['user_id' => $user->id]);

        $result = $this->sender->sendToUser($user, ['title' => 'Hello']);

        // Only inactive subs => treated as empty => early return.
        $this->assertSame(['sent' => 0, 'failed' => 0, 'deactivated' => 0], $result);
    }

    public function test_send_to_user_without_vapid_logs_warning_and_returns_zeros(): void
    {
        Log::spy();

        $user = User::factory()->client()->create();
        PushSubscription::factory()->create(['user_id' => $user->id]);

        $result = $this->sender->sendToUser($user, ['title' => 'Hello', 'body' => 'World']);

        $this->assertSame(['sent' => 0, 'failed' => 0, 'deactivated' => 0], $result);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'VAPID keys not configured'))
            ->once();
    }

    public function test_send_to_users_accepts_mixed_ids_and_models(): void
    {
        $userA = User::factory()->client()->create();
        $userB = User::factory()->employe()->create();
        PushSubscription::factory()->create(['user_id' => $userA->id]);
        PushSubscription::factory()->create(['user_id' => $userB->id]);

        // Mix a model and a raw int id — exercises the map() in sendToUsers.
        $result = $this->sender->sendToUsers([$userA, $userB->id], ['title' => 'Bulk']);

        // VAPID unconfigured => zeros, but the query path executed.
        $this->assertSame(['sent' => 0, 'failed' => 0, 'deactivated' => 0], $result);
    }

    public function test_normalize_payload_merges_defaults_and_overrides(): void
    {
        $method = new ReflectionMethod(WebPushSender::class, 'normalizePayload');
        $method->setAccessible(true);

        $normalized = $method->invoke($this->sender, [
            'title' => 'Custom title',
            'extra' => 'kept',
        ]);

        $this->assertSame('Custom title', $normalized['title']);
        $this->assertSame('kept', $normalized['extra']);
        // Defaults preserved for unspecified keys.
        $this->assertSame('', $normalized['body']);
        $this->assertSame('/icons/icon-192.png', $normalized['icon']);
        $this->assertSame('/icons/badge-72.png', $normalized['badge']);
        $this->assertSame('/', $normalized['url']);
        $this->assertNull($normalized['tag']);
        $this->assertFalse($normalized['requireInteraction']);
    }

    public function test_vapid_config_returns_null_when_keys_missing(): void
    {
        $method = new ReflectionMethod(WebPushSender::class, 'vapidConfig');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($this->sender));
    }

    public function test_vapid_config_returns_array_when_keys_present(): void
    {
        config([
            'services.webpush.public_key' => 'pub-key-123',
            'services.webpush.private_key' => 'priv-key-456',
            'services.webpush.subject' => 'mailto:hello@brio.test',
        ]);

        $method = new ReflectionMethod(WebPushSender::class, 'vapidConfig');
        $method->setAccessible(true);

        $this->assertSame([
            'subject' => 'mailto:hello@brio.test',
            'publicKey' => 'pub-key-123',
            'privateKey' => 'priv-key-456',
        ], $method->invoke($this->sender));
    }

    public function test_vapid_config_uses_default_subject(): void
    {
        config([
            'services.webpush.public_key' => 'pub',
            'services.webpush.private_key' => 'priv',
        ]);

        $method = new ReflectionMethod(WebPushSender::class, 'vapidConfig');
        $method->setAccessible(true);

        $result = $method->invoke($this->sender);

        // Falls back to the config-file default subject.
        $this->assertSame('mailto:contact@brio.local', $result['subject']);
    }
}
