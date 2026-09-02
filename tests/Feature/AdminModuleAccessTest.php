<?php

namespace Tests\Feature;

use App\Livewire\Admin\CatalogueServices;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_with_permission_can_access_services_admin_module(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-services'],
            'is_active' => true,
            'status' => 'active',
        ]);

        // La route redirige vers l'onglet du catalogue ; la capacite se mesure la ou elle vit
        // desormais — dans le composant, que `/livewire/update` atteint sans middleware de route.
        Livewire::actingAs($admin)
            ->test(CatalogueServices::class)
            ->assertOk();
    }

    public function test_admin_without_permission_cannot_access_services_admin_module(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-calendar'],
            'is_active' => true,
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)
            ->test(CatalogueServices::class)
            ->assertForbidden();
    }

    public function test_suspended_admin_is_blocked_by_active_account_middleware(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-services'],
            'is_active' => false,
            'status' => 'suspended',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.order-engine.catalog'))
            ->assertForbidden();
    }
}
