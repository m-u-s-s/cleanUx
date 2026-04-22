<?php

namespace Tests\Feature;

use App\Livewire\Admin\AutomationMissionGenerationCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAutomationMissionGenerationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_automation_center(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        Livewire::test(AutomationMissionGenerationCenter::class)
            ->assertStatus(200);
    }
}
