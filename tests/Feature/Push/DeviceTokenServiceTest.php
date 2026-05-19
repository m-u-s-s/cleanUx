<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\DeviceTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeviceTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_new_device_token(): void
    {
        $user = User::factory()->client()->create();

        $token = app(DeviceTokenService::class)->register(
            user: $user,
            token: 'apns_token_001',
            platform: 'ios',
            provider: 'apns',
            appVersion: '1.0.0',
            locale: 'fr',
        );

        $this->assertInstanceOf(DeviceToken::class, $token);
        $this->assertSame('ios', $token->platform);
        $this->assertSame('apns', $token->provider);
        $this->assertSame(DeviceToken::hashToken('apns_token_001'), $token->token_hash);
        $this->assertNotNull($token->preferences);
    }

    public function test_register_refreshes_existing_token_for_same_hash(): void
    {
        $user = User::factory()->client()->create();

        $a = app(DeviceTokenService::class)->register(
            $user, 'tok_xyz', 'android', 'fcm', '1.0', 'fr',
        );

        $b = app(DeviceTokenService::class)->register(
            $user, 'tok_xyz', 'android', 'fcm', '1.1', 'fr',
        );

        $this->assertSame($a->id, $b->id);
        $this->assertSame('1.1', $b->app_version);
        $this->assertSame(1, DeviceToken::count());
    }

    public function test_register_reuses_token_invalidated_by_another_user(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();

        $aliceToken = app(DeviceTokenService::class)->register($alice, 'shared_tok', 'ios', 'apns');
        $aliceToken->invalidate('user_unregistered');

        $bobToken = app(DeviceTokenService::class)->register($bob, 'shared_tok', 'ios', 'apns');

        // Token re-attaches to bob and clears invalidation
        $this->assertSame($aliceToken->id, $bobToken->id);
        $this->assertSame($bob->id, $bobToken->user_id);
        $this->assertNull($bobToken->invalidated_at);
    }

    public function test_register_rejects_invalid_platform(): void
    {
        $user = User::factory()->client()->create();

        $this->expectException(ValidationException::class);

        app(DeviceTokenService::class)->register($user, 'tok', 'windows', 'fcm');
    }

    public function test_register_rejects_empty_token(): void
    {
        $user = User::factory()->client()->create();

        $this->expectException(ValidationException::class);

        app(DeviceTokenService::class)->register($user, '   ', 'ios', 'apns');
    }

    public function test_unregister_invalidates_user_owned_token(): void
    {
        $user = User::factory()->client()->create();
        $token = app(DeviceTokenService::class)->register($user, 'tok_unreg', 'android', 'fcm');

        $ok = app(DeviceTokenService::class)->unregister($user, 'tok_unreg');

        $this->assertTrue($ok);
        $token->refresh();
        $this->assertNotNull($token->invalidated_at);
        $this->assertSame('user_unregistered', $token->invalidation_reason);
    }

    public function test_unregister_does_not_touch_other_users_token(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();
        $aliceToken = app(DeviceTokenService::class)->register($alice, 'shared_tok', 'ios', 'apns');

        $ok = app(DeviceTokenService::class)->unregister($bob, 'shared_tok');

        $this->assertFalse($ok);
        $aliceToken->refresh();
        $this->assertNull($aliceToken->invalidated_at);
    }

    public function test_update_preferences_merges_with_existing(): void
    {
        $user = User::factory()->client()->create();
        $token = app(DeviceTokenService::class)->register($user, 'tok_pref', 'ios', 'apns');

        $updated = app(DeviceTokenService::class)->updatePreferences($token, [
            'marketing' => true,
            'reminder' => false,
            'badkey' => 'ignored',
        ]);

        $this->assertTrue($updated->preferences['marketing']);
        $this->assertFalse($updated->preferences['reminder']);
        $this->assertArrayNotHasKey('badkey', $updated->preferences);
    }
}
