<?php

namespace Tests\Feature\TenancyV2;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenancyV2\TenantResolver;
use App\Services\TenancyV2\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TenantResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy_v2.enabled', true);
        Config::set('tenancy_v2.allowed_plans', ['basic', 'growth', 'enterprise']);
        Config::set('tenancy_v2.default_plan', 'basic');
        Config::set('tenancy_v2.header_name', 'X-Tenant-Code');
        Config::set('tenancy_v2.subdomain_pattern', '/^([a-z0-9-]+)\.(.+\..+)$/i');
        Config::set('tenancy_v2.reserved_subdomains', ['www', 'api', 'admin']);
        Config::set('tenancy_v2.tenant_user_roles', ['owner', 'admin', 'member', 'guest']);
    }

    public function test_resolves_by_header_x_tenant_code(): void
    {
        app(TenantService::class)->create(['name' => 'Acme', 'code' => 'acme', 'slug' => 'acme']);
        Config::set('tenancy_v2.resolution_strategies', ['header']);
        Config::set('tenancy_v2.default_tenant_code', '');

        $request = Request::create('/');
        $request->headers->set('X-Tenant-Code', 'acme');
        $tenant = app(TenantResolver::class)->resolve($request);

        $this->assertNotNull($tenant);
        $this->assertSame('acme', $tenant->code);
    }

    public function test_resolves_by_subdomain(): void
    {
        app(TenantService::class)->create(['name' => 'Acme', 'code' => 'acme', 'slug' => 'acme']);
        Config::set('tenancy_v2.resolution_strategies', ['subdomain']);
        Config::set('tenancy_v2.default_tenant_code', '');

        $request = Request::create('https://acme.cleanux.com/');
        $tenant = app(TenantResolver::class)->resolve($request);

        $this->assertNotNull($tenant);
        $this->assertSame('acme', $tenant->code);
    }

    public function test_reserved_subdomains_dont_resolve(): void
    {
        app(TenantService::class)->create(['name' => 'WWW Tenant', 'code' => 'www', 'slug' => 'www']);
        Config::set('tenancy_v2.resolution_strategies', ['subdomain']);
        Config::set('tenancy_v2.default_tenant_code', '');

        $request = Request::create('https://www.cleanux.com/');
        $tenant = app(TenantResolver::class)->resolve($request);

        $this->assertNull($tenant);
    }

    public function test_falls_back_to_default_tenant_code(): void
    {
        app(TenantService::class)->create(['name' => 'Main', 'code' => 'main', 'slug' => 'main']);
        Config::set('tenancy_v2.resolution_strategies', ['header']);
        Config::set('tenancy_v2.default_tenant_code', 'main');

        $request = Request::create('/');
        $tenant = app(TenantResolver::class)->resolve($request);

        $this->assertNotNull($tenant);
        $this->assertSame('main', $tenant->code);
    }

    public function test_returns_null_when_disabled(): void
    {
        Config::set('tenancy_v2.enabled', false);
        $request = Request::create('/');
        $request->headers->set('X-Tenant-Code', 'main');
        $this->assertNull(app(TenantResolver::class)->resolve($request));
    }

    public function test_strategy_order_first_match_wins(): void
    {
        app(TenantService::class)->create(['name' => 'Acme', 'code' => 'acme', 'slug' => 'acme']);
        app(TenantService::class)->create(['name' => 'Beta', 'code' => 'beta', 'slug' => 'beta']);
        Config::set('tenancy_v2.resolution_strategies', ['header', 'subdomain']);
        Config::set('tenancy_v2.default_tenant_code', '');

        $request = Request::create('https://beta.cleanux.com/');
        $request->headers->set('X-Tenant-Code', 'acme');
        $tenant = app(TenantResolver::class)->resolve($request);

        $this->assertSame('acme', $tenant->code);
    }

    public function test_resolves_by_custom_domain(): void
    {
        app(TenantService::class)->create(['name' => 'Custom', 'code' => 'custom', 'slug' => 'custom']);
        $tenant = Tenant::query()->where('code', 'custom')->first();
        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'my-cleaner.example.com',
            'is_primary' => true,
            'is_verified' => true,
        ]);
        Config::set('tenancy_v2.resolution_strategies', ['domain']);
        Config::set('tenancy_v2.default_tenant_code', '');

        $request = Request::create('https://my-cleaner.example.com/');
        $resolved = app(TenantResolver::class)->resolve($request);
        $this->assertNotNull($resolved);
        $this->assertSame('custom', $resolved->code);
    }

    public function test_does_not_resolve_unverified_custom_domain(): void
    {
        app(TenantService::class)->create(['name' => 'Custom', 'code' => 'custom', 'slug' => 'custom']);
        $tenant = Tenant::query()->where('code', 'custom')->first();
        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => 'pending.example.com',
            'is_primary' => true,
            'is_verified' => false,
        ]);
        Config::set('tenancy_v2.resolution_strategies', ['domain']);
        Config::set('tenancy_v2.default_tenant_code', '');

        $request = Request::create('https://pending.example.com/');
        $this->assertNull(app(TenantResolver::class)->resolve($request));
    }

    public function test_does_not_resolve_suspended_tenant(): void
    {
        app(TenantService::class)->create(['name' => 'Acme', 'code' => 'acme', 'slug' => 'acme']);
        $t = Tenant::query()->where('code', 'acme')->first();
        app(TenantService::class)->suspend($t, 'Test suspension reason');

        Config::set('tenancy_v2.resolution_strategies', ['header']);
        Config::set('tenancy_v2.default_tenant_code', '');
        $request = Request::create('/');
        $request->headers->set('X-Tenant-Code', 'acme');
        $this->assertNull(app(TenantResolver::class)->resolve($request));
    }
}
