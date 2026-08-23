<?php

namespace Tests\Feature\Missions;

use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\MissionReinforcementRequest;
use App\Models\MissionTaskSegment;
use App\Models\User;
use App\Services\Missions\TeamLeadOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coverage for the reachable public behavior of TeamLeadOperationsService. */
class TeamLeadOperationsServiceCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TeamLeadOperationsService
    {
        return app(TeamLeadOperationsService::class);
    }

    public function test_request_reinforcement_persists_explicit_payload(): void
    {
        $mission = Mission::factory()->create();
        $team = FieldTeam::factory()->create();
        $segment = MissionTaskSegment::factory()->create([
            'mission_id' => $mission->id,
            'field_team_id' => null,
        ]);
        $requester = User::factory()->create();

        $request = $this->service()->requestReinforcement($segment, $requester, [
            'field_team_id' => $team->id,
            'priority' => 'critique',
            'requested_members' => 3,
            'requested_minutes' => 120,
            'reason' => 'Charge imprévue sur le chantier.',
        ]);

        $this->assertInstanceOf(MissionReinforcementRequest::class, $request);
        $this->assertDatabaseHas('mission_reinforcement_requests', [
            'id' => $request->id,
            'mission_id' => $mission->id,
            'mission_task_segment_id' => $segment->id,
            'requested_by_user_id' => $requester->id,
            'field_team_id' => $team->id,
            'status' => 'open',
            'priority' => 'critique',
            'requested_members' => 3,
            'requested_minutes' => 120,
            'reason' => 'Charge imprévue sur le chantier.',
        ]);
    }

    public function test_request_reinforcement_applies_defaults_and_clamps(): void
    {
        $team = FieldTeam::factory()->create();
        $segment = MissionTaskSegment::factory()->create([
            'mission_id' => null,
            'field_team_id' => $team->id,
        ]);
        $requester = User::factory()->create();

        // requested_members below 1 clamps to 1, requested_minutes below 15 clamps to 15,
        // priority/reason fall back to defaults, field_team_id inherits from the segment,
        // and the missing missionBatchDay relation resolves null-safely to a null batch id.
        $request = $this->service()->requestReinforcement($segment, $requester, [
            'requested_members' => 0,
            'requested_minutes' => 5,
        ]);

        $this->assertSame('open', $request->status);
        $this->assertSame('haute', $request->priority);
        $this->assertSame(1, $request->requested_members);
        $this->assertSame(15, $request->requested_minutes);
        $this->assertSame($team->id, $request->field_team_id);
        $this->assertNull($request->mission_batch_id);
        $this->assertNotEmpty($request->reason);
    }
}
