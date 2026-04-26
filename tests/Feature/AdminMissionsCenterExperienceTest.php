<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMissionsCenterExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_missions_center(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->get('/admin/missions');

        $response->assertOk();
        $response->assertSee('Centre missions');
        $response->assertSee('Filtres missions');
        $response->assertSee('Points d’attention');
    }
}
