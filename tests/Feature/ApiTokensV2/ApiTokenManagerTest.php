<?php

namespace Tests\Feature\ApiTokensV2;

use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Models\User;
use App\Services\ApiTokensV2\ApiTokenManager;
use Database\Seeders\ApiTokenScopesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiTokenManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApiTokenScopesSeeder::class);
        Config::set('api_tokens_v2.allowed_scopes', [
            'read:bookings', 'write:bookings', 'admin:everything',
        ]);
        Config::set('api_tokens_v2.default_expiry_days', 365);
        Config::set('api_tokens_v2.rotation_grace_hours', 24);
        Config::set('api_tokens_v2.owner_roles', ['api_partner', 'admin', 'client', 'provider']);
        Config::set('api_tokens_v2.default_owner_role', 'api_partner');
    }

    public function test_create_for_user_persists_token_with_metadata(): void
    {
        $user = User::factory()->create();
        $new = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'B2B Acme prod',
            'display_name' => 'Acme prod token',
            'scopes' => ['read:bookings', 'write:bookings'],
            'owner_role' => 'api_partner',
            'rate_limit_per_minute' => 300,
        ]);

        $this->assertNotNull($new->plainTextToken);
        $token = $new->accessToken;
        $this->assertInstanceOf(PersonalAccessTokenV2::class, $token);
        $this->assertSame('Acme prod token', $token->display_name);
        $this->assertSame('api_partner', $token->owner_role);
        $this->assertSame(300, (int) $token->rate_limit_per_minute);
        $this->assertSame(['read:bookings', 'write:bookings'], (array) $token->abilities);
        $this->assertNotNull($token->expires_at);
    }

    public function test_create_rejects_unknown_scopes(): void
    {
        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'Bad',
            'scopes' => ['read:bookings', 'forbidden:scope'],
        ]);
    }

    public function test_create_rejects_admin_scope_for_non_admin_role(): void
    {
        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'Bad role',
            'scopes' => ['admin:everything'],
            'owner_role' => 'api_partner',
        ]);
    }

    public function test_create_allows_admin_scope_for_admin_role(): void
    {
        $user = User::factory()->create();
        $new = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'Admin token',
            'scopes' => ['admin:everything'],
            'owner_role' => 'admin',
        ]);
        $this->assertContains('admin:everything', (array) $new->accessToken->abilities);
    }

    public function test_rotate_issues_new_token_and_grace_period_on_old(): void
    {
        $user = User::factory()->create();
        $original = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'Rotate me',
            'scopes' => ['read:bookings'],
            'owner_role' => 'api_partner',
        ])->accessToken;

        $new = app(ApiTokenManager::class)->rotate($original);

        $this->assertNotSame($original->id, $new->accessToken->id);
        $this->assertSame($original->id, $new->accessToken->rotated_from_token_id);
        $this->assertNotNull($original->fresh()->rotation_grace_until);
        $this->assertTrue($original->fresh()->rotation_grace_until->isFuture());
        $this->assertSame(['read:bookings'], (array) $new->accessToken->abilities);
    }

    public function test_suspend_requires_minimum_reason_length(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'S', 'scopes' => ['read:bookings'],
        ])->accessToken;

        $this->expectException(ValidationException::class);
        app(ApiTokenManager::class)->suspend($token, 'no');
    }

    public function test_suspend_marks_token_unusable(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'S2', 'scopes' => ['read:bookings'],
        ])->accessToken;

        $suspended = app(ApiTokenManager::class)->suspend($token, 'Activité suspecte détectée');
        $this->assertTrue($suspended->isSuspended());
        $this->assertFalse($suspended->isUsable());
    }

    public function test_unsuspend_restores_token(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'S3', 'scopes' => ['read:bookings'],
        ])->accessToken;
        app(ApiTokenManager::class)->suspend($token, 'Test suspension reason');
        $restored = app(ApiTokenManager::class)->unsuspend($token);

        $this->assertFalse($restored->isSuspended());
        $this->assertTrue($restored->isUsable());
    }

    public function test_revoke_deletes_token(): void
    {
        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'Revoke me', 'scopes' => ['read:bookings'],
        ])->accessToken;
        $id = $token->id;
        app(ApiTokenManager::class)->revoke($token);
        $this->assertSame(0, PersonalAccessTokenV2::query()->where('id', $id)->count());
    }

    public function test_effective_rate_limit_falls_back_to_config(): void
    {
        Config::set('api_tokens_v2.default_rate_limit_per_minute', 120);
        Config::set('api_tokens_v2.admin_rate_limit_per_minute', 600);

        $user = User::factory()->create();
        $token = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'Default rate', 'scopes' => ['read:bookings'],
        ])->accessToken;
        $this->assertSame(120, $token->effectiveRateLimit());

        $adminToken = app(ApiTokenManager::class)->createForUser($user, [
            'name' => 'Admin rate', 'scopes' => ['admin:everything'], 'owner_role' => 'admin',
        ])->accessToken;
        $this->assertSame(600, $adminToken->effectiveRateLimit());
    }
}
