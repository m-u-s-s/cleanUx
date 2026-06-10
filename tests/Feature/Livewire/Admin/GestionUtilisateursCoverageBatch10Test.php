<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\GestionUtilisateurs;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GestionUtilisateursCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(array $overrides = []): User
    {
        return User::factory()->admin()->create(array_merge([
            'permissions' => ['manage-users', 'manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ], $overrides));
    }

    public function test_render_ok_exposes_view_data(): void
    {
        $this->makeAdmin();
        ServiceZone::factory()->create(['name' => 'Alpha Zone']);

        Livewire::actingAs($this->makeAdmin())
            ->test(GestionUtilisateurs::class)
            ->assertOk()
            ->assertViewHas('users')
            ->assertViewHas('zones')
            ->assertViewHas('permissionOptions')
            ->assertViewHas('allAvailableTrades');
    }

    public function test_updating_filters_reset_pagination(): void
    {
        Livewire::actingAs($this->makeAdmin())
            ->test(GestionUtilisateurs::class)
            ->set('roleFilter', User::ROLE_EMPLOYE)
            ->set('search', 'foo')
            ->set('accessScopeFilter', 'all')
            ->assertOk();
    }

    public function test_toggle_activation_deactivates_then_reactivates(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->client()->create(['is_active' => true]);

        $component = Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class);

        $component->call('toggleActivation', $target->id);
        $target->refresh();
        $this->assertFalse((bool) $target->is_active);
        $this->assertSame('inactive', $target->status);

        $component->call('toggleActivation', $target->id);
        $target->refresh();
        $this->assertTrue((bool) $target->is_active);
        $this->assertSame('active', $target->status);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'security.user_activation_toggled',
            'user_id' => $admin->id,
        ]);
    }

    public function test_update_role_normalizes_entreprise_alias(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->client()->create();

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->call('updateRole', $target->id, 'entreprise');

        $this->assertSame(User::ROLE_ENTREPRISE, $target->refresh()->role);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'security.user_role_updated',
            'user_id' => $admin->id,
        ]);
    }

    public function test_update_role_keeps_plain_role(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->client()->create();

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->call('updateRole', $target->id, User::ROLE_EMPLOYE);

        $this->assertSame(User::ROLE_EMPLOYE, $target->refresh()->role);
    }

    public function test_edit_security_loads_target_then_cancel_resets(): void
    {
        $admin = $this->makeAdmin();
        $zone = ServiceZone::factory()->create();
        $target = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $zone->id,
            'permissions' => ['manage-users'],
        ]);

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->call('editSecurity', $target->id)
            ->assertSet('editingUserId', $target->id)
            ->assertSet('securityAccessScope', User::ACCESS_SCOPE_ZONE)
            ->assertSet('securityManagedZoneId', $zone->id)
            ->assertSet('securityPermissions', ['manage-users'])
            ->call('cancelSecurityEdit')
            ->assertSet('editingUserId', null)
            ->assertSet('securityAccessScope', User::ACCESS_SCOPE_ALL)
            ->assertSet('securityManagedZoneId', null)
            ->assertSet('securityPermissions', []);
    }

    public function test_save_security_zone_scope_requires_a_zone(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->call('editSecurity', $target->id)
            ->set('securityAccessScope', User::ACCESS_SCOPE_ZONE)
            ->set('securityManagedZoneId', null)
            ->call('saveSecurity')
            ->assertHasErrors('securityManagedZoneId');

        $this->assertNotSame(User::ACCESS_SCOPE_ZONE, $target->refresh()->access_scope);
    }

    public function test_save_security_readonly_scope_clears_managed_zone(): void
    {
        $admin = $this->makeAdmin();
        $zone = ServiceZone::factory()->create();
        $target = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $zone->id,
        ]);

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->call('editSecurity', $target->id)
            ->set('securityAccessScope', User::ACCESS_SCOPE_READONLY)
            ->set('securityManagedZoneId', $zone->id)
            ->set('securityPermissions', ['manage-users', 'manage-users', ''])
            ->call('saveSecurity')
            ->assertHasNoErrors()
            ->assertSet('editingUserId', null);

        $target->refresh();
        $this->assertSame(User::ACCESS_SCOPE_READONLY, $target->access_scope);
        $this->assertNull($target->managed_service_zone_id);
        $this->assertSame(['manage-users'], $target->permissions);
    }

    public function test_render_filters_by_access_scope_and_search(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->client()->create([
            'name' => 'Zoe Searchable',
            'access_scope' => User::ACCESS_SCOPE_ALL,
        ]);
        User::factory()->client()->create([
            'name' => 'Nina Other',
            'access_scope' => User::ACCESS_SCOPE_ALL,
        ]);

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            // 'none' exercises the whereNull branch (NOT NULL column => no matches).
            ->set('accessScopeFilter', 'none')
            ->assertOk()
            ->assertDontSee('Zoe Searchable')
            // explicit scope branch + search branch.
            ->set('accessScopeFilter', User::ACCESS_SCOPE_ALL)
            ->set('search', 'Searchable')
            ->assertSee('Zoe Searchable')
            ->assertDontSee('Nina Other');
    }

    public function test_render_filters_by_non_client_role(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->employe()->create(['name' => 'Eddy Employe']);
        User::factory()->client()->create(['name' => 'Carla Client']);

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->set('roleFilter', User::ROLE_EMPLOYE)
            ->assertOk()
            ->assertSee('Eddy Employe')
            ->assertDontSee('Carla Client');
    }

    public function test_zone_scoped_admin_only_sees_own_zone_in_zones_property(): void
    {
        $zoneA = ServiceZone::factory()->create(['name' => 'Owned Zone']);
        ServiceZone::factory()->create(['name' => 'Other Zone']);

        $admin = $this->makeAdmin([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $zoneA->id,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->assertOk();

        $zones = $component->viewData('zones');
        $this->assertCount(1, $zones);
        $this->assertSame($zoneA->id, $zones->first()->id);
    }

    public function test_non_admin_is_forbidden_from_toggle_activation(): void
    {
        $client = User::factory()->client()->create();
        $target = User::factory()->client()->create(['is_active' => true]);

        // EnforcesAdminAccess now blocks a non-admin at mount/boot (before any action).
        Livewire::actingAs($client)
            ->test(GestionUtilisateurs::class)
            ->assertForbidden();

        $this->assertTrue((bool) $target->refresh()->is_active);
    }

    public function test_admin_cannot_update_own_role(): void
    {
        $admin = $this->makeAdmin();

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->call('updateRole', $admin->id, User::ROLE_EMPLOYE)
            ->assertForbidden();
    }
}
