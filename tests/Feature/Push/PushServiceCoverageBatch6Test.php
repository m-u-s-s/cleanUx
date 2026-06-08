<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\Push\Providers\PushMockProvider;
use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushSendRequest;
use App\Services\Push\PushSendResult;
use App\Services\Push\PushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushServiceCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(PushProviderInterface::class, PushMockProvider::class);
        Config::set('push.enabled', true);
        Config::set('push.rate_limits.per_token_per_minute', 10);
        Config::set('push.rate_limits.per_user_per_minute', 30);
        // Ensure platform routing falls back to the injected provider by default.
        Config::set('push.fcm.project_id', null);
        Config::set('push.fcm.credentials_path', null);
        Config::set('push.apns.key_path', null);
        Config::set('push.apns.key_id', null);
    }

    protected function makeToken(?User $user = null, array $overrides = []): DeviceToken
    {
        $user ??= User::factory()->client()->create();
        $raw = $overrides['token'] ?? 'tok_'.uniqid();

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

    public function test_provider_getter_returns_injected_provider(): void
    {
        $svc = app(PushService::class);

        $this->assertInstanceOf(PushMockProvider::class, $svc->provider());
        $this->assertSame('mock', $svc->provider()->name());
    }

    public function test_dispatch_records_source_type_and_id(): void
    {
        $token = $this->makeToken();
        $source = User::factory()->admin()->create();

        $notif = app(PushService::class)->dispatch(
            token: $token,
            title: 'With source',
            body: 'b',
            source: $source,
        );

        $this->assertNotNull($notif);
        $this->assertSame(User::class, $notif->source_type);
        $this->assertSame($source->getKey(), $notif->source_id);
    }

    public function test_dispatch_routes_to_fcm_provider_for_android_when_configured(): void
    {
        Http::fake();
        Config::set('push.fcm.project_id', 'demo-project');

        $token = $this->makeToken(overrides: ['platform' => DeviceToken::PLATFORM_ANDROID]);

        $notif = app(PushService::class)->dispatch(
            token: $token,
            title: 't',
            body: 'b',
        );

        // FcmPushProvider chosen by routing; send() returns a config failure
        // because push.providers.fcm.project_id is unset -> graceful FAILED.
        $this->assertNotNull($notif);
        $this->assertSame('fcm', $notif->provider);
        $this->assertSame(PushNotification::STATUS_FAILED, $notif->status);
        $this->assertSame('fcm_config', $notif->failure_code);
    }

    public function test_dispatch_routes_ios_to_fcm_fallback_when_only_fcm_configured(): void
    {
        Http::fake();
        Config::set('push.fcm.project_id', 'demo-project');

        $token = $this->makeToken(overrides: ['platform' => 'ios']);

        $notif = app(PushService::class)->dispatch($token, 't', 'b');

        $this->assertNotNull($notif);
        $this->assertSame('fcm', $notif->provider);
        $this->assertSame(PushNotification::STATUS_FAILED, $notif->status);
    }

    public function test_dispatch_routes_ios_to_apns_provider_when_apns_configured(): void
    {
        Http::fake();
        Config::set('push.apns.key_path', '/nonexistent/key.p8');

        $token = $this->makeToken(overrides: ['platform' => 'ios']);

        $notif = app(PushService::class)->dispatch($token, 't', 'b');

        // ApnsPushProvider chosen by routing; send() returns apns_config because
        // push.providers.apns.* are unset -> graceful FAILED, no real network.
        $this->assertNotNull($notif);
        $this->assertSame('apns', $notif->provider);
        $this->assertSame(PushNotification::STATUS_FAILED, $notif->status);
        $this->assertSame('apns_config', $notif->failure_code);
    }

    public function test_dispatch_marks_failed_when_provider_throws(): void
    {
        $throwing = new class implements PushProviderInterface
        {
            public function name(): string
            {
                return 'boom';
            }

            public function supportsPlatforms(): array
            {
                return ['ios', 'android', 'web'];
            }

            public function send(PushSendRequest $request): PushSendResult
            {
                throw new \RuntimeException('provider exploded');
            }
        };
        $this->app->instance(PushProviderInterface::class, $throwing);

        $token = $this->makeToken();

        $notif = app(PushService::class)->dispatch($token, 't', 'b');

        $this->assertNotNull($notif);
        $this->assertSame(PushNotification::STATUS_FAILED, $notif->status);
        $this->assertSame('provider exploded', $notif->failed_reason);
        $this->assertNotNull($notif->failed_at);
    }

    public function test_dispatch_to_user_uses_per_device_idempotency_keys(): void
    {
        $user = User::factory()->client()->create();
        $this->makeToken($user, ['platform' => 'ios']);
        $this->makeToken($user, ['platform' => 'android']);

        $results = app(PushService::class)->dispatchToUser(
            user: $user,
            title: 'Hi',
            body: 'All devices',
            idempotencyKey: 'batch6:user:idem',
            source: $user,
        );

        $this->assertCount(2, $results);
        $keys = array_map(fn ($r) => $r->idempotency_key, $results);
        $this->assertContains('batch6:user:idem:dev0', $keys);
        $this->assertContains('batch6:user:idem:dev1', $keys);

        // Re-dispatching with the same base key returns the existing records (idempotent).
        $again = app(PushService::class)->dispatchToUser(
            user: $user,
            title: 'Hi again',
            body: 'All devices',
            idempotencyKey: 'batch6:user:idem',
            source: $user,
        );
        $this->assertCount(2, $again);
        $this->assertSame(2, PushNotification::count());
    }

    public function test_dispatch_skipped_records_source_when_opted_out(): void
    {
        $user = User::factory()->client()->create();
        $token = $this->makeToken($user, ['preferences' => [
            'transactional' => true,
            'marketing' => false,
        ]]);

        $notif = app(PushService::class)->dispatch(
            token: $token,
            title: 'Promo',
            body: 'Marketing blast',
            category: PushNotification::CATEGORY_MARKETING,
            source: $user,
        );

        $this->assertSame(PushNotification::STATUS_OPTED_OUT, $notif->status);
        $this->assertSame(User::class, $notif->source_type);
        $this->assertSame($user->getKey(), $notif->source_id);
    }
}
