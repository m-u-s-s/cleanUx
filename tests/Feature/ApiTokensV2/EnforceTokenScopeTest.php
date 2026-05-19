<?php

namespace Tests\Feature\ApiTokensV2;

use App\Models\User;
use App\Services\ApiTokensV2\ApiTokenManager;
use Database\Seeders\ApiTokenScopesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnforceTokenScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApiTokenScopesSeeder::class);
        Config::set('api_tokens_v2.allowed_scopes', [
            'read:bookings', 'write:bookings', 'admin:everything',
        ]);
        Config::set('api_tokens_v2.owner_roles', ['api_partner', 'admin']);
        Config::set('api_tokens_v2.default_owner_role', 'api_partner');

        Route::middleware(['auth:sanctum', 'api_scope:read:bookings'])
            ->get('/test/scoped-read', fn () => ['ok' => true]);
        Route::middleware(['auth:sanctum', 'api_scope:write:bookings'])
            ->get('/test/scoped-write', fn () => ['ok' => true]);
    }

    public function test_token_with_required_scope_passes(): void
    {
        $user = User::factory()->create();
        $new = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'reader', 'scopes' => ['read:bookings'],
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $new->plainTextToken])
            ->getJson('/test/scoped-read');
        $response->assertOk();
    }

    public function test_token_without_required_scope_rejected_403(): void
    {
        $user = User::factory()->create();
        $new = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'reader', 'scopes' => ['read:bookings'],
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $new->plainTextToken])
            ->getJson('/test/scoped-write');
        $response->assertStatus(403);
        $this->assertSame('missing_scope', $response->json('error'));
    }

    public function test_admin_everything_wildcard_grants_any_scope(): void
    {
        $user = User::factory()->create();
        $new = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'wild', 'scopes' => ['admin:everything'], 'owner_role' => 'admin',
        ]);

        $r1 = $this->withHeaders(['Authorization' => 'Bearer ' . $new->plainTextToken])
            ->getJson('/test/scoped-read');
        $r2 = $this->withHeaders(['Authorization' => 'Bearer ' . $new->plainTextToken])
            ->getJson('/test/scoped-write');
        $r1->assertOk();
        $r2->assertOk();
    }

    public function test_suspended_token_returns_403_token_suspended(): void
    {
        $user = User::factory()->create();
        $new = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'sus', 'scopes' => ['read:bookings'],
        ]);
        app(ApiTokenManager::class)->suspend($new->accessToken, 'test suspension scenario');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $new->plainTextToken])
            ->getJson('/test/scoped-read');
        $response->assertStatus(403);
        $this->assertSame('token_suspended', $response->json('error'));
    }

    public function test_expired_token_is_rejected_by_sanctum_auth_layer(): void
    {
        // Sanctum's auth:sanctum middleware rejects expired tokens before our scope
        // middleware sees them — that's the cheaper layer to validate at.
        $user = User::factory()->create();
        $new = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'old', 'scopes' => ['read:bookings'],
        ]);
        $new->accessToken->forceFill(['expires_at' => now()->subDay()])->save();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $new->plainTextToken])
            ->getJson('/test/scoped-read');
        $response->assertStatus(401);
    }

    public function test_isUsable_returns_false_for_expired_or_suspended_or_rotated(): void
    {
        $user = User::factory()->create();
        $new = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'multi', 'scopes' => ['read:bookings'],
        ]);
        $token = $new->accessToken;

        // active
        $this->assertTrue($token->isUsable());

        // suspended
        $token->forceFill(['suspended_at' => now()])->save();
        $this->assertFalse($token->fresh()->isUsable());

        // restore, then expire
        $token->forceFill(['suspended_at' => null, 'expires_at' => now()->subDay()])->save();
        $this->assertFalse($token->fresh()->isUsable());

        // restore, then rotation grace expired
        $token->forceFill(['expires_at' => now()->addDay(), 'rotation_grace_until' => now()->subHour()])->save();
        $this->assertFalse($token->fresh()->isUsable());
    }
}
