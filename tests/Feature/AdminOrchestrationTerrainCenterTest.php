<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrchestrationTerrainCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_orchestration_center(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'permissions' => ['*'],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.orchestration'))
            ->assertOk();
    }
}
