<?php

namespace Tests\Feature\TenancyV2;

use App\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Services\TenancyV2\TenantContext;
use App\Services\TenancyV2\TenantService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test model factice : crée une table jetable `belongs_to_tenant_test_models`
 * qui utilise le trait BelongsToTenant. Permet de tester la mécanique du global
 * scope + auto-fill sans toucher aux models business.
 */
class FakeTenantScopedModel extends Model
{
    use BelongsToTenant;

    protected $table = 'belongs_to_tenant_test_models';
    protected $fillable = ['name', 'tenant_id'];
    public $timestamps = false;
}

class BelongsToTenantTraitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy_v2.allowed_plans', ['basic', 'growth', 'enterprise']);
        Config::set('tenancy_v2.default_plan', 'basic');

        // table factice
        Schema::create('belongs_to_tenant_test_models', function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
        });

        // Reset context before each test
        app(TenantContext::class)->reset();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('belongs_to_tenant_test_models');
        parent::tearDown();
    }

    public function test_creating_with_context_auto_fills_tenant_id(): void
    {
        $tenant = app(TenantService::class)->create(['name' => 'Acme']);
        app(TenantContext::class)->set($tenant);

        $row = FakeTenantScopedModel::query()->create(['name' => 'auto']);
        $this->assertSame($tenant->id, $row->tenant_id);
    }

    public function test_query_filters_by_current_tenant_via_global_scope(): void
    {
        $a = app(TenantService::class)->create(['name' => 'A']);
        $b = app(TenantService::class)->create(['name' => 'B']);

        app(TenantContext::class)->set($a);
        FakeTenantScopedModel::query()->create(['name' => 'a1']);
        FakeTenantScopedModel::query()->create(['name' => 'a2']);

        app(TenantContext::class)->set($b);
        FakeTenantScopedModel::query()->create(['name' => 'b1']);

        $this->assertSame(1, FakeTenantScopedModel::query()->count());
        $this->assertSame('b1', FakeTenantScopedModel::query()->first()->name);

        app(TenantContext::class)->set($a);
        $this->assertSame(2, FakeTenantScopedModel::query()->count());
    }

    public function test_without_global_scope_bypasses_filter(): void
    {
        $a = app(TenantService::class)->create(['name' => 'A']);
        $b = app(TenantService::class)->create(['name' => 'B']);

        app(TenantContext::class)->set($a);
        FakeTenantScopedModel::query()->create(['name' => 'a1']);
        app(TenantContext::class)->set($b);
        FakeTenantScopedModel::query()->create(['name' => 'b1']);

        // platform-wide query
        $all = FakeTenantScopedModel::query()->withoutGlobalScope('tenant')->get();
        $this->assertCount(2, $all);
    }

    public function test_no_context_means_no_scope_filter(): void
    {
        $a = app(TenantService::class)->create(['name' => 'A']);
        app(TenantContext::class)->set($a);
        FakeTenantScopedModel::query()->create(['name' => 'a1']);

        app(TenantContext::class)->reset();
        $this->assertSame(1, FakeTenantScopedModel::query()->count());
    }

    public function test_run_for_temporary_context(): void
    {
        $a = app(TenantService::class)->create(['name' => 'A']);
        $b = app(TenantService::class)->create(['name' => 'B']);

        app(TenantContext::class)->set($a);
        $insertedId = app(TenantContext::class)->runFor($b, function () {
            return FakeTenantScopedModel::query()->create(['name' => 'b1'])->id;
        });

        // Après runFor, le context est restauré à $a
        $this->assertSame($a->id, app(TenantContext::class)->current()->id);

        // La row a bien été créée avec tenant_b
        $row = FakeTenantScopedModel::query()->withoutGlobalScope('tenant')->find($insertedId);
        $this->assertSame($b->id, $row->tenant_id);
    }
}
