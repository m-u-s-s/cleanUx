<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedCentersRouteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_integrated_advanced_centers(): void
    {
        // Ce test balaie plusieurs centres a la fois : la liste etroite d'origine n'en couvrait
        // que deux, et `EnforceModuleGate` refuse desormais les autres.
        $admin = User::factory()->adminComplet()->create(['is_active' => true]);

        $this->actingAs($admin);

        $this->get(route('admin.teams.partners'))->assertOk();
        $this->get(route('admin.b2b.operations'))->assertOk();
        $this->get(route('admin.international'))->assertRedirect('/admin/catalogue?onglet=marche');
        $this->get(route('admin.orchestration'))->assertOk();
        $this->get(route('admin.automation'))->assertOk();
    }

    public function test_non_admin_cannot_access_integrated_admin_centers(): void
    {
        $employee = User::factory()->employe()->create(['is_active' => true]);

        $this->actingAs($employee)
            ->get(route('admin.teams.partners'))->assertForbidden();
    }
}
