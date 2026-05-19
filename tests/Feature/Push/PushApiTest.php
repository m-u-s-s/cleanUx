<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\DeviceTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_requires_authentication(): void
    {
        $this->postJson('/api/client/devices/register', [
            'token' => 'foo',
            'platform' => 'ios',
            'provider' => 'apns',
        ])->assertStatus(401);
    }

    public function test_register_validates_required_fields(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/devices/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'platform', 'provider']);
    }

    public function test_register_validates_platform_enum(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/devices/register', [
            'token' => 'tok',
            'platform' => 'windows',
            'provider' => 'fcm',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_register_creates_device_token_and_returns_id(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/client/devices/register', [
            'token' => 'apns_token_001',
            'platform' => 'ios',
            'provider' => 'apns',
            'app_version' => '1.0.0',
            'locale' => 'fr',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['ok', 'device_token_id', 'platform', 'provider', 'preferences']);

        $this->assertSame(1, DeviceToken::query()->where('user_id', $user->id)->count());
    }

    public function test_unregister_invalidates_token(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $token = app(DeviceTokenService::class)->register($user, 'tok_unreg', 'android', 'fcm');

        $this->postJson('/api/client/devices/unregister', ['token' => 'tok_unreg'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $token->refresh();
        $this->assertNotNull($token->invalidated_at);
    }

    public function test_unregister_returns_404_for_unknown_token(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/devices/unregister', ['token' => 'nonexistent'])
            ->assertStatus(404)
            ->assertJson(['ok' => false]);
    }

    public function test_index_returns_only_active_tokens_of_current_user(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();

        $svc = app(DeviceTokenService::class);
        $svc->register($alice, 'alice_active', 'ios', 'apns');
        $invalid = $svc->register($alice, 'alice_invalid', 'android', 'fcm');
        $invalid->invalidate('test');
        $svc->register($bob, 'bob_active', 'ios', 'apns');

        Sanctum::actingAs($alice);

        $response = $this->getJson('/api/client/devices');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('ios', $data[0]['platform']);
    }

    public function test_update_preferences_persists_opt_out(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $token = app(DeviceTokenService::class)->register($user, 'tok_pref', 'ios', 'apns');

        $response = $this->patchJson("/api/client/devices/{$token->id}/preferences", [
            'preferences' => [
                'marketing' => true,
                'reminder' => false,
            ],
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('preferences.marketing'));
        $this->assertFalse($response->json('preferences.reminder'));
    }

    public function test_update_preferences_returns_403_when_token_belongs_to_other_user(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();

        $aliceToken = app(DeviceTokenService::class)->register($alice, 'alice_tok', 'ios', 'apns');

        Sanctum::actingAs($bob);

        $this->patchJson("/api/client/devices/{$aliceToken->id}/preferences", [
            'preferences' => ['marketing' => true],
        ])->assertStatus(403);
    }
}
