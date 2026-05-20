<?php

namespace Tests\Feature\TenancyV2;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\TenancyV2\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy_v2.allowed_plans', ['basic', 'growth', 'enterprise']);
        Config::set('tenancy_v2.default_plan', 'basic');
        Config::set('tenancy_v2.trial_days_default', 0);
        Config::set('tenancy_v2.tenant_user_roles', ['owner', 'admin', 'member', 'guest']);
    }

    public function test_create_tenant_with_minimal_data(): void
    {
        $t = app(TenantService::class)->create(['name' => 'Acme']);
        $this->assertSame('Acme', $t->name);
        $this->assertSame('acme', $t->slug);
        $this->assertSame('basic', $t->plan_code);
        $this->assertSame(Tenant::STATUS_ACTIVE, $t->status);
        $this->assertNotNull($t->activated_at);
    }

    public function test_create_with_trial_starts_in_trial_status(): void
    {
        $t = app(TenantService::class)->create([
            'name' => 'Trial Co',
            'trial_days' => 14,
        ]);
        $this->assertSame(Tenant::STATUS_TRIAL, $t->status);
        $this->assertNotNull($t->trial_ends_at);
        $this->assertTrue($t->trial_ends_at->isFuture());
    }

    public function test_create_rejects_duplicate_code(): void
    {
        app(TenantService::class)->create(['name' => 'Acme']);
        $this->expectException(ValidationException::class);
        app(TenantService::class)->create(['name' => 'Acme']);
    }

    public function test_create_rejects_invalid_plan(): void
    {
        $this->expectException(ValidationException::class);
        app(TenantService::class)->create(['name' => 'Bad', 'plan_code' => 'platinum']);
    }

    public function test_create_with_billing_owner_attaches_as_tenant_user(): void
    {
        $user = User::factory()->create();
        $t = app(TenantService::class)->create([
            'name' => 'Owned',
            'billing_owner_user_id' => $user->id,
        ]);
        $this->assertSame(1, TenantUser::query()->where('tenant_id', $t->id)->where('user_id', $user->id)->count());
        $tu = TenantUser::query()->where('tenant_id', $t->id)->first();
        $this->assertSame(TenantUser::ROLE_OWNER, $tu->role);
    }

    public function test_create_with_primary_domain_creates_domain_row(): void
    {
        $t = app(TenantService::class)->create([
            'name' => 'Domain Co',
            'primary_domain' => 'domain.test',
        ]);
        $this->assertSame(1, $t->domains()->count());
        $this->assertSame('domain.test', $t->domains()->first()->domain);
    }

    public function test_suspend_requires_reason(): void
    {
        $t = app(TenantService::class)->create(['name' => 'Acme']);
        $this->expectException(ValidationException::class);
        app(TenantService::class)->suspend($t, 'no');
    }

    public function test_suspend_marks_suspended(): void
    {
        $t = app(TenantService::class)->create(['name' => 'Acme']);
        $s = app(TenantService::class)->suspend($t, 'Activité suspecte');
        $this->assertSame(Tenant::STATUS_SUSPENDED, $s->status);
        $this->assertSame('Activité suspecte', $s->suspended_reason);
    }

    public function test_activate_resets_status_to_active(): void
    {
        $t = app(TenantService::class)->create(['name' => 'Acme']);
        app(TenantService::class)->suspend($t, 'test reason');
        $a = app(TenantService::class)->activate($t);
        $this->assertSame(Tenant::STATUS_ACTIVE, $a->status);
        $this->assertNull($a->suspended_reason);
    }

    public function test_change_plan_validates_and_records_history(): void
    {
        $t = app(TenantService::class)->create(['name' => 'Acme']);
        $changed = app(TenantService::class)->changePlan($t, 'growth');
        $this->assertSame('growth', $changed->plan_code);
        $this->assertSame('basic', data_get($changed->metadata, 'previous_plan'));
    }

    public function test_attach_user_creates_tenant_user_row(): void
    {
        $t = app(TenantService::class)->create(['name' => 'Acme']);
        $user = User::factory()->create();
        $tu = app(TenantService::class)->attachUser($t, $user, TenantUser::ROLE_MEMBER);
        $this->assertSame(TenantUser::ROLE_MEMBER, $tu->role);
        $this->assertTrue((bool) $tu->is_active);
    }

    public function test_detach_user_sets_left_at(): void
    {
        $t = app(TenantService::class)->create(['name' => 'Acme']);
        $user = User::factory()->create();
        app(TenantService::class)->attachUser($t, $user, TenantUser::ROLE_MEMBER);
        app(TenantService::class)->detachUser($t, $user);
        $tu = TenantUser::query()->where('tenant_id', $t->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($tu->left_at);
        $this->assertFalse((bool) $tu->is_active);
    }
}
