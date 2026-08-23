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
        // `['*']` N'ACCORDAIT RIEN, et deux choses manquaient en meme temps.
        $admin = User::factory()->adminComplet()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.orchestration'))
            ->assertOk();
    }
}
