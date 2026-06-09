<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\MissionActions;
use App\Models\User;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

class MissionActionsCoverageBatch11Test extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    public function test_lead_employee_can_mount(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => MissionStatus::ASSIGNED]);
        $this->actingAs($scenario['employee']);

        Livewire::test(MissionActions::class, ['mission' => $scenario['mission']])
            ->assertOk()
            ->assertSet('successMessage', null)
            ->assertSet('errorMessage', null);
    }

    public function test_set_en_route_transitions_and_dispatches_tracking_event(): void
    {
        Notification::fake();
        $scenario = $this->createMissionPortalContext(['status' => MissionStatus::ASSIGNED]);
        $this->actingAs($scenario['employee']);

        Livewire::test(MissionActions::class, ['mission' => $scenario['mission']])
            ->call('setEnRoute')
            ->assertSet('successMessage', 'Mission passée en route.')
            ->assertSet('errorMessage', null)
            ->assertDispatched('mission-en-route-start-tracking');

        $this->assertSame(
            MissionStatus::EN_ROUTE,
            $scenario['mission']->fresh()->status
        );
    }

    public function test_set_en_route_for_unassigned_user_sets_error_message(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => MissionStatus::ASSIGNED]);

        $stranger = User::factory()->employe()->create([
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->actingAs($stranger);

        Livewire::test(MissionActions::class, ['mission' => $scenario['mission']])
            ->call('setEnRoute')
            ->assertSet('successMessage', null)
            ->assertSet('errorMessage', 'Utilisateur non affecté à cette mission.');
    }

    public function test_set_arrived_generates_start_code(): void
    {
        Notification::fake();
        $scenario = $this->createMissionPortalContext(['status' => MissionStatus::EN_ROUTE]);
        $this->actingAs($scenario['employee']);

        Livewire::test(MissionActions::class, ['mission' => $scenario['mission']])
            ->call('setArrived')
            ->assertSet('successMessage', 'Arrivée confirmée. Code de début généré.')
            ->assertSet('errorMessage', null);

        $this->assertSame(
            MissionStatus::ARRIVED,
            $scenario['mission']->fresh()->status
        );
        $this->assertNotNull(session('mission_start_code_'.$scenario['mission']->id));
    }

    public function test_start_mission_requires_six_digit_code(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => MissionStatus::ARRIVED]);
        $this->actingAs($scenario['employee']);

        Livewire::test(MissionActions::class, ['mission' => $scenario['mission']])
            ->set('startCode', '12')
            ->call('startMission')
            ->assertHasErrors(['startCode']);
    }

    public function test_arrive_then_start_mission_succeeds(): void
    {
        Notification::fake();
        $scenario = $this->createMissionPortalContext(['status' => MissionStatus::EN_ROUTE]);
        $mission = $scenario['mission'];
        $this->actingAs($scenario['employee']);

        $component = Livewire::test(MissionActions::class, ['mission' => $mission])
            ->call('setArrived');

        $code = session('mission_start_code_'.$mission->id);
        $this->assertNotNull($code);

        $component
            ->set('startCode', $code)
            ->call('startMission')
            ->assertSet('successMessage', 'Mission démarrée avec succès.')
            ->assertSet('errorMessage', null)
            ->assertSet('startCode', '')
            ->assertSet('generatedStartCode', null);

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
    }

    public function test_prepare_end_code_before_started_sets_error_message(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => MissionStatus::ASSIGNED]);
        $this->actingAs($scenario['employee']);

        Livewire::test(MissionActions::class, ['mission' => $scenario['mission']])
            ->call('prepareEndCode')
            ->assertSet('successMessage', null)
            ->assertSet('errorMessage', 'La mission doit être démarrée avant de générer un code de fin.');
    }

    public function test_prepare_end_code_when_started_succeeds(): void
    {
        $scenario = $this->createMissionPortalContext([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now()->subHour(),
        ]);
        $mission = $scenario['mission'];
        $this->actingAs($scenario['employee']);

        Livewire::test(MissionActions::class, ['mission' => $mission])
            ->call('prepareEndCode')
            ->assertSet('successMessage', 'Code de fin généré.')
            ->assertSet('errorMessage', null);

        $this->assertNotNull(session('mission_end_code_'.$mission->id));
    }

    public function test_finish_mission_requires_six_digit_code(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => MissionStatus::STARTED]);
        $this->actingAs($scenario['employee']);

        Livewire::test(MissionActions::class, ['mission' => $scenario['mission']])
            ->set('endCode', 'abc')
            ->call('finishMission')
            ->assertHasErrors(['endCode']);
    }

    public function test_prepare_then_finish_mission_succeeds(): void
    {
        Notification::fake();
        $scenario = $this->createMissionPortalContext([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now()->subHour(),
        ]);
        $mission = $scenario['mission'];
        $this->actingAs($scenario['employee']);

        $component = Livewire::test(MissionActions::class, ['mission' => $mission])
            ->call('prepareEndCode');

        $code = session('mission_end_code_'.$mission->id);
        $this->assertNotNull($code);

        $component
            ->set('endCode', $code)
            ->call('finishMission')
            ->assertSet('successMessage', 'Mission terminée avec succès.')
            ->assertSet('errorMessage', null)
            ->assertSet('endCode', '')
            ->assertSet('generatedEndCode', null);

        $this->assertSame(MissionStatus::COMPLETED, $mission->fresh()->status);
    }
}
