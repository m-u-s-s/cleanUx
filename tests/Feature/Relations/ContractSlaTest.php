<?php

namespace Tests\Feature\Relations;

use App\Models\ContractSlaEvent;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Services\Contracts\ContractSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractSlaTest extends TestCase
{
    use RefreshDatabase;

    private function missionUnderContract(int $responseHours, int $resolutionHours): Mission
    {
        $contract = OrganizationContract::factory()->create([
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'sla_response_hours' => $responseHours,
            'sla_resolution_hours' => $resolutionHours,
        ]);

        return Mission::create([
            'status' => 'planned',
            'organization_contract_id' => $contract->id,
            'planned_start_at' => now()->addDay(),
        ]);
    }

    public function test_arming_sla_sets_due_dates_and_pending_events(): void
    {
        $mission = $this->missionUnderContract(4, 24);

        app(ContractSlaService::class)->armForMission($mission);

        $mission->refresh();
        $this->assertNotNull($mission->sla_response_due_at);
        $this->assertNotNull($mission->sla_resolution_due_at);
        $this->assertSame(2, ContractSlaEvent::where('mission_id', $mission->id)->where('status', 'pending')->count());
    }

    public function test_scan_marks_breached_and_escalates_once(): void
    {
        $mission = $this->missionUnderContract(4, 24);
        app(ContractSlaService::class)->armForMission($mission);

        // Force l'échéance dans le passé sans satisfaction.
        ContractSlaEvent::where('mission_id', $mission->id)->update(['due_at' => now()->subHour()]);

        app(ContractSlaService::class)->scan();
        $this->assertSame(2, ContractSlaEvent::where('mission_id', $mission->id)->where('status', 'escalated')->count());

        // Idempotent : un 2e scan ne ré-escalade pas (escalated_at déjà posé).
        $before = ContractSlaEvent::where('mission_id', $mission->id)->pluck('escalated_at')->toArray();
        app(ContractSlaService::class)->scan();
        $after = ContractSlaEvent::where('mission_id', $mission->id)->pluck('escalated_at')->toArray();
        $this->assertEquals($before, $after);
    }

    public function test_scan_marks_met_when_resolved_before_due(): void
    {
        $mission = $this->missionUnderContract(4, 24);
        app(ContractSlaService::class)->armForMission($mission);

        $mission->update(['status' => 'completed', 'actual_end_at' => now()]);

        app(ContractSlaService::class)->scan();

        $this->assertSame('met', ContractSlaEvent::where('mission_id', $mission->id)->where('kind', 'resolution')->value('status'));
    }
}
