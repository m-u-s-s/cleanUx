<?php

namespace Tests\Feature;

use App\Livewire\Employe\TeamLeadOperationsCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamLeadOperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_lead_can_render_operations_center_placeholder(): void
    {
        $lead = User::factory()->create(['role' => User::ROLE_EMPLOYE]);

        $this->actingAs($lead);

        Livewire::test(TeamLeadOperationsCenter::class)
            ->assertStatus(200);
    }
}
