<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\Push\Providers\PushMockProvider;
use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PushServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PushProviderInterface::class, PushMockProvider::class);
        Config::set('push.enabled', true);
        Config::set('push.rate_limits.per_token_per_minute', 10);
        Config::set('push.rate_limits.per_user_per_minute', 30);
    }

    protected function makeToken(?User $user = null, array $overrides = []): DeviceToken
    {
        $user ??= User::factory()->client()->create();
        $raw = $overrides['token'] ?? 'tok_' . uniqid();

        return DeviceToken::create(array_merge([
            'user_id' => $user->id,
            'platform' => DeviceToken::PLATFORM_ANDROID,
            'provider' => DeviceToken::PROVIDER_MOCK,
            'token' => $raw,
            'token_hash' => DeviceToken::hashToken($raw),
            'preferences' => [
                'transactional' => true,
                'verification' => true,
                'reminder' => true,
                'marketing' => false,
            ],
            'last_used_at' => now(),
        ], $overrides));
    }

    public function test_dispatch_creates_push_notification_record(): void
    {
        $token = $this->makeToken();

        $notif = app(PushService::class)->dispatch(
            token: $token,
            title: 'Hello',
            body: 'Test push',
            data: ['booking_id' => 1],
            category: PushNotification::CATEGORY_TRANSACTIONAL,
        );

        $this->assertNotNull($notif);
        $this->assertSame(PushNotification::STATUS_SENT, $notif->status);
        $this->assertStringStartsWith('mock_push_', $notif->external_id);
        $this->assertSame($token->id, $notif->device_token_id);
        $this->assertSame($token->user_id, $notif->user_id);
    }

    public function test_dispatch_is_idempotent_with_same_key(): void
    {
        $token = $this->makeToken();
        $svc = app(PushService::class);

        $a = $svc->dispatch($token, 'A', 'first', idempotencyKey: 'test:idem:001');
        $b = $svc->dispatch($token, 'B', 'second', idempotencyKey: 'test:idem:001');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, PushNotification::count());
    }

    public function test_dispatch_marks_opted_out_when_user_disabled_category(): void
    {
        $token = $this->makeToken(overrides: ['preferences' => [
            'transactional' => true,
            'marketing' => false,
        ]]);

        $notif = app(PushService::class)->dispatch(
            token: $token,
            title: 'Promo',
            body: 'Marketing blast',
            category: PushNotification::CATEGORY_MARKETING,
        );

        $this->assertSame(PushNotification::STATUS_OPTED_OUT, $notif->status);
    }

    public function test_dispatch_invalidates_token_when_provider_signals_invalid(): void
    {
        $token = $this->makeToken(overrides: ['token' => 'invalid_token_xyz', 'token_hash' => DeviceToken::hashToken('invalid_token_xyz')]);

        $notif = app(PushService::class)->dispatch(
            token: $token,
            title: 't',
            body: 'b',
        );

        $this->assertSame(PushNotification::STATUS_INVALID_TOKEN, $notif->status);

        $token->refresh();
        $this->assertNotNull($token->invalidated_at);
        $this->assertSame('mock_invalid_token', $token->invalidation_reason);
    }

    public function test_dispatch_marks_failed_when_provider_returns_fail(): void
    {
        $token = $this->makeToken(overrides: ['token' => 'fail_token_abc', 'token_hash' => DeviceToken::hashToken('fail_token_abc')]);

        $notif = app(PushService::class)->dispatch(
            token: $token,
            title: 't',
            body: 'b',
        );

        $this->assertSame(PushNotification::STATUS_FAILED, $notif->status);
        $this->assertSame('mock_fail', $notif->failure_code);

        $token->refresh();
        $this->assertNull($token->invalidated_at);
    }

    public function test_dispatch_skipped_when_push_disabled(): void
    {
        Config::set('push.enabled', false);
        $token = $this->makeToken();

        $notif = app(PushService::class)->dispatch($token, 't', 'b');

        $this->assertNull($notif);
        $this->assertSame(0, PushNotification::count());
    }

    public function test_dispatch_skipped_for_inactive_token(): void
    {
        $token = $this->makeToken();
        $token->invalidate('test');

        $notif = app(PushService::class)->dispatch($token, 't', 'b');

        $this->assertSame(PushNotification::STATUS_INVALID_TOKEN, $notif->status);
    }

    public function test_rate_limit_per_token_creates_rate_limited_record(): void
    {
        Config::set('push.rate_limits.per_token_per_minute', 2);
        $token = $this->makeToken();
        $svc = app(PushService::class);

        $svc->dispatch($token, 't', '1');
        $svc->dispatch($token, 't', '2');
        $third = $svc->dispatch($token, 't', '3');

        $this->assertSame(PushNotification::STATUS_RATE_LIMITED, $third->status);
    }

    public function test_dispatch_to_user_sends_to_all_active_tokens(): void
    {
        $user = User::factory()->client()->create();
        $a = $this->makeToken($user, ['platform' => 'ios']);
        $b = $this->makeToken($user, ['platform' => 'android']);
        $inactive = $this->makeToken($user);
        $inactive->invalidate('test');

        $results = app(PushService::class)->dispatchToUser(
            user: $user,
            title: 'Hi',
            body: 'All devices',
            category: PushNotification::CATEGORY_TRANSACTIONAL,
        );

        $this->assertCount(2, $results);
        $tokenIds = array_map(fn ($r) => $r->device_token_id, $results);
        $this->assertContains($a->id, $tokenIds);
        $this->assertContains($b->id, $tokenIds);
    }
}
