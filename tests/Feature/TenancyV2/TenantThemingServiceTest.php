<?php

namespace Tests\Feature\TenancyV2;

use App\Models\Tenant;
use App\Services\TenancyV2\TenantService;
use App\Services\TenancyV2\TenantThemingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TenantThemingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy_v2.allowed_plans', ['basic', 'growth', 'enterprise']);
        Config::set('tenancy_v2.default_plan', 'basic');
        Config::set('tenancy_v2.plans', [
            'basic' => ['name' => 'Basic', 'custom_theming' => false, 'custom_domain' => false],
            'growth' => ['name' => 'Growth', 'custom_theming' => true, 'custom_domain' => true],
            'enterprise' => ['name' => 'Enterprise', 'custom_theming' => true, 'custom_domain' => true],
        ]);
        Config::set('tenancy_v2.theming_defaults', [
            'primary_color' => '#4F46E5',
            'app_name' => 'CleanUx',
            'support_email' => 'support@cleanux.com',
        ]);
    }

    public function test_null_tenant_returns_defaults(): void
    {
        $cfg = app(TenantThemingService::class)->configFor(null);
        $this->assertSame('#4F46E5', $cfg['primary_color']);
        $this->assertSame('CleanUx', $cfg['app_name']);
    }

    public function test_basic_plan_ignores_custom_theming(): void
    {
        $t = app(TenantService::class)->create([
            'name' => 'Acme',
            'plan_code' => 'basic',
            'theming' => ['primary_color' => '#DC2626'],
        ]);
        $cfg = app(TenantThemingService::class)->configFor($t);
        $this->assertSame('#4F46E5', $cfg['primary_color']);   // defaults wins
    }

    public function test_growth_plan_allows_custom_theming_override(): void
    {
        $t = app(TenantService::class)->create([
            'name' => 'Acme',
            'plan_code' => 'growth',
            'theming' => ['primary_color' => '#DC2626', 'app_name' => 'Acme Cleaning'],
        ]);
        $cfg = app(TenantThemingService::class)->configFor($t);
        $this->assertSame('#DC2626', $cfg['primary_color']);
        $this->assertSame('Acme Cleaning', $cfg['app_name']);
        // Non-overridden default still present
        $this->assertSame('support@cleanux.com', $cfg['support_email']);
    }

    public function test_update_theming_only_persists_whitelisted_keys(): void
    {
        $t = app(TenantService::class)->create(['name' => 'Acme', 'plan_code' => 'growth']);
        $updated = app(TenantThemingService::class)->updateTheming($t, [
            'primary_color' => '#10B981',
            'malicious_key' => 'should be ignored',
            'app_name' => 'My App',
        ]);
        $this->assertSame('#10B981', data_get($updated->theming, 'primary_color'));
        $this->assertSame('My App', data_get($updated->theming, 'app_name'));
        $this->assertArrayNotHasKey('malicious_key', (array) $updated->theming);
    }
}
