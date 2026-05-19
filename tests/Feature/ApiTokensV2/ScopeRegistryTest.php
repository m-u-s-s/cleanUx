<?php

namespace Tests\Feature\ApiTokensV2;

use App\Services\ApiTokensV2\ScopeRegistry;
use Database\Seeders\ApiTokenScopesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ScopeRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApiTokenScopesSeeder::class);
        Config::set('api_tokens_v2.allowed_scopes', [
            'read:bookings', 'write:bookings', 'admin:everything',
        ]);
    }

    public function test_filter_for_role_separates_valid_and_invalid(): void
    {
        $r = app(ScopeRegistry::class)->filterForRole(
            ['read:bookings', 'forbidden:scope', 'write:bookings'],
            'api_partner',
        );

        $this->assertContains('read:bookings', $r['valid']);
        $this->assertContains('write:bookings', $r['valid']);
        $this->assertContains('forbidden:scope', $r['invalid']);
    }

    public function test_required_role_rejects_when_not_matching(): void
    {
        $r = app(ScopeRegistry::class)->filterForRole(['admin:everything'], 'api_partner');
        $this->assertContains('admin:everything', $r['invalid']);

        $r2 = app(ScopeRegistry::class)->filterForRole(['admin:everything'], 'admin');
        $this->assertContains('admin:everything', $r2['valid']);
    }

    public function test_token_has_scope_respects_admin_everything_wildcard(): void
    {
        $reg = app(ScopeRegistry::class);
        $this->assertTrue($reg->tokenHasScope(['admin:everything'], 'write:bookings'));
        $this->assertTrue($reg->tokenHasScope(['read:bookings'], 'read:bookings'));
        $this->assertFalse($reg->tokenHasScope(['read:bookings'], 'write:bookings'));
    }

    public function test_is_dangerous_picks_up_config_or_db_flag(): void
    {
        Config::set('api_tokens_v2.dangerous_scopes', ['write:bookings']);
        $reg = app(ScopeRegistry::class);
        $this->assertTrue($reg->isDangerous('write:bookings'));
        $this->assertTrue($reg->isDangerous('admin:users'));  // db flag from seeder
        $this->assertFalse($reg->isDangerous('read:bookings'));
    }
}
