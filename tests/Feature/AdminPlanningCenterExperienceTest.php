<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlanningCenterExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_planning_page_renders_new_operational_sections(): void
    {
        $admin = User::factory()->admin()->create([
            'access_scope' => 'all',
            'permissions' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.planning'))
            ->assertOk()
            ->assertSee('Centre de planification opérationnelle')
            ->assertSee('Filtres de pilotage')
            ->assertSee('Agenda hebdomadaire')
            ->assertSee('Employés les plus sollicités');
    }

    public function test_admin_planning_page_can_render_focus_intervention_cards(): void
    {
        $admin = User::factory()->admin()->create([
            'access_scope' => 'all',
            'permissions' => [],
            'is_active' => true,
        ]);

        $rdv = Booking::factory()->confirme()->create([
            'date' => now()->format('Y-m-d'),
            'heure' => '09:00:00',
            'priorite' => 'urgente',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.planning'))
            ->assertOk()
            ->assertSee($rdv->client->name)
            ->assertSee($rdv->service_display_name);
    }
}
