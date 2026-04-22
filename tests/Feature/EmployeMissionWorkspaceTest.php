<?php

namespace Tests\Feature;

use App\Livewire\Employe\MissionsEmploye;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

class EmployeMissionWorkspaceTest extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    public function test_employee_can_select_rendez_vous_and_open_workspace(): void
    {
        $scenario = $this->createMissionPortalContext([
            'status' => 'assigned',
        ]);

        $this->actingAs($scenario['employee']);

        Livewire::test(MissionsEmploye::class)
            ->call('selectRdv', $scenario['rendezVous']->id)
            ->assertSet('selectedRdvId', $scenario['rendezVous']->id)
            ->assertSee('Mission sélectionnée')
            ->assertSee('Mission #'.$scenario['mission']->id)
            ->assertSee($scenario['client']->name);
    }

    public function test_employee_can_clear_selected_workspace(): void
    {
        $scenario = $this->createMissionPortalContext([
            'status' => 'assigned',
        ]);

        $this->actingAs($scenario['employee']);

        Livewire::test(MissionsEmploye::class)
            ->call('selectRdv', $scenario['rendezVous']->id)
            ->call('clearSelectedRdv')
            ->assertSet('selectedRdvId', null)
            ->assertSee('Aucune mission ouverte');
    }
}
