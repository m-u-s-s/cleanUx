<?php

namespace Tests\Feature;

use App\Livewire\Admin\PlatformModulesCenter;
use App\Models\OrganizationAccount;
use App\Models\PlatformModule;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformModulesCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_permission_can_access_modules_center(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-modules'],
            'is_active' => true,
            'status' => 'active',
        ]);

        PlatformModule::factory()->create([
            'key' => 'clients.entreprise',
            'name' => 'Clients entreprise',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.modules'))
            ->assertOk()
            ->assertSee('Centre de contrôle des modules');
    }

    public function test_admin_without_permission_cannot_access_modules_center(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-users'],
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.modules'))
            ->assertForbidden();
    }

    public function test_admin_can_save_module_audience_rules(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-modules'],
            'is_active' => true,
            'status' => 'active',
        ]);

        $module = PlatformModule::factory()->create([
            'key' => 'calendar.sync',
            'name' => 'Synchronisation agenda',
            'rollout_strategy' => 'global',
            'settings' => [],
        ]);

        $organization = OrganizationAccount::factory()->create();
        $zone = ServiceZone::factory()->create();

        $this->actingAs($admin);

        Livewire::test(PlatformModulesCenter::class)
            ->call('editModule', $module->id)
            ->set('rollout_strategy', 'organization')
            ->set('allowed_roles', ['admin', 'entreprise'])
            ->set('allowed_plans', ['premium'])
            ->set('allowed_organization_ids', [$organization->id])
            ->set('allowed_zone_ids', [$zone->id])
            ->set('is_enabled', true)
            ->set('is_locked', true)
            ->call('save')
            ->assertHasNoErrors();

        $module->refresh();

        $this->assertSame('organization', $module->rollout_strategy);
        $this->assertTrue($module->is_enabled);
        $this->assertTrue($module->is_locked);
        $this->assertSame(['admin', 'entreprise'], $module->settings['allowed_roles']);
        $this->assertSame(['premium'], $module->settings['allowed_plans']);
        $this->assertSame([$organization->id], $module->settings['allowed_organization_ids']);
        $this->assertSame([$zone->id], $module->settings['allowed_zone_ids']);
    }
}
