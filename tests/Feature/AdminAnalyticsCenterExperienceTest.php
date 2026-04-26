<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsCenterExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_analytics_center(): void
    {
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-analytics'],
        ]);

        $response = $this->actingAs($admin)->get('/admin/analytics');

        $response->assertOk();
        $response->assertSee('Centre analytics');
        $response->assertSee('Mix marché');
        $response->assertSee('KPIs par zone');
    }
}
