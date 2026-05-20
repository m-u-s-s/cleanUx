<?php

namespace Tests\Feature\TenancyV2;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\TenancyV2\TenantContext;
use App\Services\TenancyV2\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenancyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy_v2.allowed_plans', ['basic', 'growth', 'enterprise']);
        Config::set('tenancy_v2.default_plan', 'basic');
        Config::set('tenancy_v2.tenant_user_roles', ['owner', 'admin', 'member', 'guest']);
        Config::set('tenancy_v2.plans', [
            'basic' => ['name' => 'Basic', 'custom_theming' => false],
            'growth' => ['name' => 'Growth', 'custom_theming' => true],
        ]);
        Config::set('tenancy_v2.theming_defaults', ['primary_color' => '#4F46E5', 'app_name' => 'CleanUx']);
    }

    public function test_current_tenant_endpoint_returns_null_when_no_context(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/tenancy/me');
        $response->assertOk();
        $this->assertNull($response->json('tenant'));
        $this->assertSame('CleanUx', $response->json('theming.app_name'));
    }

    public function test_current_tenant_endpoint_returns_tenant_when_set_in_context(): void
    {
        $user = User::factory()->create();
        $tenant = app(TenantService::class)->create(['name' => 'Acme', 'plan_code' => 'growth']);
        app(TenantContext::class)->set($tenant);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v2/tenancy/me');
        $response->assertOk();
        $this->assertSame('acme', $response->json('tenant.code'));
    }

    public function test_admin_create_tenant_via_api(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tenancy-v2/tenants', [
            'name' => 'New Co',
            'plan_code' => 'basic',
        ]);
        $response->assertCreated();
        $this->assertSame('new-co', $response->json('tenant.code'));
        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_admin_create_validates_plan(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/tenancy-v2/tenants', [
            'name' => 'Bad',
            'plan_code' => 'platinum',
        ])->assertStatus(422);
    }

    public function test_admin_suspend_tenant(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = app(TenantService::class)->create(['name' => 'Acme']);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/tenancy-v2/tenants/{$tenant->id}/suspend", [
            'reason' => 'Non-paiement détecté',
        ]);
        $response->assertOk();
        $this->assertSame(Tenant::STATUS_SUSPENDED, $tenant->fresh()->status);
    }

    public function test_admin_suspend_validates_reason_length(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = app(TenantService::class)->create(['name' => 'Acme']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/tenancy-v2/tenants/{$tenant->id}/suspend", [
            'reason' => 'no',
        ])->assertStatus(422);
    }

    public function test_admin_change_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = app(TenantService::class)->create(['name' => 'Acme', 'plan_code' => 'basic']);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/tenancy-v2/tenants/{$tenant->id}/change-plan", [
            'plan_code' => 'growth',
        ]);
        $response->assertOk();
        $this->assertSame('growth', $tenant->fresh()->plan_code);
    }

    public function test_admin_update_theming_persists(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = app(TenantService::class)->create(['name' => 'Acme', 'plan_code' => 'growth']);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/tenancy-v2/tenants/{$tenant->id}/theming", [
            'primary_color' => '#DC2626',
            'app_name' => 'Acme Cleaning',
        ]);
        $response->assertOk();
        $this->assertSame('#DC2626', data_get($tenant->fresh()->theming, 'primary_color'));
    }

    public function test_admin_add_and_verify_domain(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = app(TenantService::class)->create(['name' => 'Acme']);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/tenancy-v2/tenants/{$tenant->id}/domains", [
            'domain' => 'acme.example.com',
            'is_primary' => true,
        ]);
        $response->assertCreated();
        $domainId = (int) $response->json('domain.id');
        $this->assertFalse((bool) $response->json('domain.is_verified'));

        $verify = $this->postJson("/api/admin/tenancy-v2/domains/{$domainId}/verify");
        $verify->assertOk();
        $this->assertTrue((bool) $verify->json('domain.is_verified'));
        $this->assertSame(TenantDomain::SSL_READY, $verify->json('domain.ssl_status'));
    }

    public function test_admin_attach_user_to_tenant(): void
    {
        $admin = User::factory()->admin()->create();
        $newUser = User::factory()->create();
        $tenant = app(TenantService::class)->create(['name' => 'Acme']);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/tenancy-v2/tenants/{$tenant->id}/users", [
            'user_id' => $newUser->id,
            'role' => 'member',
        ]);
        $response->assertOk();
        $this->assertSame('member', $response->json('tenant_user.role'));
    }

    public function test_admin_create_requires_auth(): void
    {
        $this->postJson('/api/admin/tenancy-v2/tenants', [
            'name' => 'X',
        ])->assertStatus(401);
    }
}
