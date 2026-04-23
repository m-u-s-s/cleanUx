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
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-entreprises', 'manage-services'],
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.teams.partners'))->assertOk();
        $this->get(route('admin.b2b.operations'))->assertOk();
        $this->get(route('admin.international'))->assertOk();
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
