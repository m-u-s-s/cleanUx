<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceCenterExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_finance_center(): void
    {
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-finance'],
        ]);

        $response = $this->actingAs($admin)->get('/admin/finance');

        $response->assertOk();
        $response->assertSee('Centre finance');
        $response->assertSee('Pipeline finance');
        $response->assertSee('Workspace finance');
    }
}
