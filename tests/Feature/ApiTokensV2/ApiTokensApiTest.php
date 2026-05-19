<?php

namespace Tests\Feature\ApiTokensV2;

use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Models\User;
use App\Services\ApiTokensV2\ApiTokenManager;
use Database\Seeders\ApiTokenScopesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTokensApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApiTokenScopesSeeder::class);
        Config::set('api_tokens_v2.allowed_scopes', [
            'read:bookings', 'write:bookings', 'read:providers', 'admin:everything',
        ]);
        Config::set('api_tokens_v2.owner_roles', ['api_partner', 'admin']);
        Config::set('api_tokens_v2.default_owner_role', 'api_partner');
        Config::set('api_tokens_v2.default_expiry_days', 365);
    }

    public function test_scopes_catalog_endpoint_returns_seeded_scopes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/tokens/scopes');
        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('read:bookings', $codes);
        $this->assertContains('admin:everything', $codes);
    }

    public function test_create_token_returns_plain_text_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v2/tokens/me/tokens', [
            'name' => 'My integration',
            'scopes' => ['read:bookings'],
        ]);
        $response->assertCreated();
        $this->assertNotEmpty($response->json('plain_text_token'));
        $this->assertSame('My integration', $response->json('token.name'));
    }

    public function test_create_validates_scopes_whitelist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v2/tokens/me/tokens', [
            'name' => 'Bad',
            'scopes' => ['this:does:not:exist'],
        ]);
        $response->assertStatus(422);
    }

    public function test_list_my_tokens_returns_own_tokens(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        app(ApiTokenManager::class)->createForUser($user, ['name' => 'mine', 'scopes' => ['read:bookings']]);
        app(ApiTokenManager::class)->createForUser($other, ['name' => 'his', 'scopes' => ['read:bookings']]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v2/tokens/me/tokens');
        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('mine', $names);
        $this->assertNotContains('his', $names);
    }

    public function test_rotate_my_token_returns_new_plain_text(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'rot', 'scopes' => ['read:bookings'],
        ])->accessToken;

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v2/tokens/me/tokens/{$token->id}/rotate");
        $response->assertOk();
        $this->assertNotEmpty($response->json('plain_text_token'));
        $this->assertNotNull($response->json('old_token_grace_until'));
    }

    public function test_rotate_other_user_token_forbidden(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($other, [
            'name' => 'other', 'scopes' => ['read:bookings'],
        ])->accessToken;

        Sanctum::actingAs($user);
        $this->postJson("/api/v2/tokens/me/tokens/{$token->id}/rotate")
            ->assertStatus(403);
    }

    public function test_revoke_my_token_deletes_it(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'kill', 'scopes' => ['read:bookings'],
        ])->accessToken;

        Sanctum::actingAs($user);
        $response = $this->deleteJson("/api/v2/tokens/me/tokens/{$token->id}");
        $response->assertOk();
        $this->assertSame(0, PersonalAccessTokenV2::query()->where('id', $token->id)->count());
    }

    public function test_admin_suspend_returns_token_marked_suspended(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'tobe-suspended', 'scopes' => ['read:bookings'],
        ])->accessToken;

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/api-tokens-v2/tokens/{$token->id}/suspend", [
            'reason' => 'Activité anormale détectée par fraud engine',
        ]);
        $response->assertOk();
        $this->assertTrue($response->json('token.is_suspended'));
    }

    public function test_admin_suspend_validates_reason_min_length(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 't', 'scopes' => ['read:bookings'],
        ])->accessToken;

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/api-tokens-v2/tokens/{$token->id}/suspend", [
            'reason' => 'no',
        ])->assertStatus(422);
    }

    public function test_admin_list_returns_all_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        app(ApiTokenManager::class)->createForUser($a, ['name' => 'A', 'scopes' => ['read:bookings']]);
        app(ApiTokenManager::class)->createForUser($b, ['name' => 'B', 'scopes' => ['read:bookings']]);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/api-tokens-v2/tokens');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }
}
