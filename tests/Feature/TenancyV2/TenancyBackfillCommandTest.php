<?php

namespace Tests\Feature\TenancyV2;

use App\Models\User;
use App\Services\TenancyV2\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenancyBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy_v2.allowed_plans', ['basic', 'growth', 'enterprise']);
        Config::set('tenancy_v2.default_plan', 'basic');
    }

    public function test_backfill_assigns_main_tenant_to_users_without_tenant_id(): void
    {
        if (! Schema::hasColumn('users', 'tenant_id')) {
            $this->markTestSkipped('users.tenant_id column not migrated.');
        }

        // Crée tenant main
        $tenant = app(TenantService::class)->create(['name' => 'Main', 'code' => 'main', 'slug' => 'main']);
        // Quelques users sans tenant_id
        $u1 = User::factory()->create(['tenant_id' => null]);
        $u2 = User::factory()->create(['tenant_id' => null]);
        // Et un user déjà tenant
        $u3 = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->artisan('tenancy:backfill --tenant=main --tables=users')
            ->expectsOutputToContain('rows sans tenant_id')
            ->expectsOutputToContain('Backfill terminé')
            ->assertSuccessful();

        $this->assertSame($tenant->id, (int) $u1->fresh()->tenant_id);
        $this->assertSame($tenant->id, (int) $u2->fresh()->tenant_id);
        $this->assertSame($tenant->id, (int) $u3->fresh()->tenant_id);
    }

    public function test_backfill_dry_run_does_not_update(): void
    {
        if (! Schema::hasColumn('users', 'tenant_id')) {
            $this->markTestSkipped('users.tenant_id column not migrated.');
        }

        app(TenantService::class)->create(['name' => 'Main', 'code' => 'main', 'slug' => 'main']);
        $u = User::factory()->create(['tenant_id' => null]);

        $this->artisan('tenancy:backfill --tenant=main --tables=users --dry-run')
            ->expectsOutputToContain('rows sans tenant_id')
            ->assertSuccessful();

        $this->assertNull($u->fresh()->tenant_id);
    }

    public function test_backfill_fails_when_tenant_code_missing(): void
    {
        $this->artisan('tenancy:backfill --tenant=nonexistent --tables=users')
            ->expectsOutputToContain('introuvable')
            ->assertFailed();
    }
}
