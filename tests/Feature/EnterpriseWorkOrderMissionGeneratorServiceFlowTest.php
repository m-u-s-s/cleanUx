<?php

namespace Tests\Feature;

use App\Models\EnterpriseWorkOrder;
use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Services\Missions\EnterpriseWorkOrderMissionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Behavioural coverage for the parts of the generator that are runnable under
 * the test schema: the approval guard of runForApprovedWorkOrder() and every
 * branch of applyContractToMission() (the contract-stamping helper invoked for
 * each materialised mission).
 *
 * The ensureBatch / seed / materialize happy-path writes to generation columns
 * (generated_batch_id, auto_generate_missions, generation_status, ...) that no
 * migration provisions, so they cannot be exercised against the in-memory DB.
 */
class EnterpriseWorkOrderMissionGeneratorServiceFlowTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EnterpriseWorkOrderMissionGeneratorService
    {
        return app(EnterpriseWorkOrderMissionGeneratorService::class);
    }

    private function invokeApplyContract(Mission $mission, MissionBatch $batch): void
    {
        $ref = new \ReflectionMethod($this->service(), 'applyContractToMission');
        $ref->setAccessible(true);
        $ref->invoke($this->service(), $mission, $batch);
    }

    public function test_run_for_unapproved_work_order_is_skipped_without_side_effects(): void
    {
        $org = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        $workOrder = EnterpriseWorkOrder::create([
            'organization_account_id' => $org->id,
            'service_catalog_id' => $service->id,
            'title' => 'Pending WO',
            'reference' => 'WO-FLOW-PENDING',
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);

        $result = $this->service()->runForApprovedWorkOrder($workOrder);

        $this->assertSame('skipped_not_approved', $result['status']);
        $this->assertNull($result['batch']);
        $this->assertSame(0, $result['missions_created']);
        // The guard must short-circuit before any batch is generated.
        $this->assertSame(0, MissionBatch::query()->count());
    }

    public function test_apply_contract_is_noop_when_batch_has_no_work_order(): void
    {
        $org = OrganizationAccount::factory()->create();

        // Batch deliberately created without enterprise_work_order_id.
        $batch = MissionBatch::create([
            'organization_account_id' => $org->id,
            'reference' => 'WO-FLOW-NOWO',
            'name' => 'Standalone batch',
            'status' => 'planned',
        ]);

        $mission = Mission::create([
            'mission_batch_id' => $batch->id,
            'status' => 'planned',
            'planned_start_at' => now(),
        ]);

        $this->invokeApplyContract($mission, $batch);

        $mission->refresh();
        $this->assertNull($mission->organization_contract_id);
        $this->assertNull($mission->provider_organization_id);
        $this->assertNull($mission->sla_response_due_at);
    }

    public function test_apply_contract_is_noop_when_work_order_has_no_contract(): void
    {
        $org = OrganizationAccount::factory()->create();

        $workOrder = EnterpriseWorkOrder::create([
            'organization_account_id' => $org->id,
            'organization_contract_id' => null,
            'title' => 'WO without contract',
            'reference' => 'WO-FLOW-NOC',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        $batch = MissionBatch::create([
            'organization_account_id' => $org->id,
            'enterprise_work_order_id' => $workOrder->id,
            'reference' => 'WO-'.$workOrder->reference,
            'name' => 'Batch',
            'status' => 'planned',
        ]);

        $mission = Mission::create([
            'enterprise_work_order_id' => $workOrder->id,
            'mission_batch_id' => $batch->id,
            'status' => 'planned',
            'planned_start_at' => now(),
        ]);

        $this->invokeApplyContract($mission, $batch);

        $mission->refresh();
        $this->assertNull($mission->organization_contract_id);
        $this->assertNull($mission->provider_organization_id);
    }

    public function test_apply_contract_is_noop_when_contract_has_no_provider(): void
    {
        $org = OrganizationAccount::factory()->create();

        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $org->id,
            'provider_organization_id' => null,
            'status' => 'active',
        ]);

        $workOrder = EnterpriseWorkOrder::create([
            'organization_account_id' => $org->id,
            'organization_contract_id' => $contract->id,
            'title' => 'WO contract no provider',
            'reference' => 'WO-FLOW-NOPROV',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        $batch = MissionBatch::create([
            'organization_account_id' => $org->id,
            'enterprise_work_order_id' => $workOrder->id,
            'reference' => 'WO-'.$workOrder->reference,
            'name' => 'Batch',
            'status' => 'planned',
        ]);

        $mission = Mission::create([
            'enterprise_work_order_id' => $workOrder->id,
            'mission_batch_id' => $batch->id,
            'status' => 'planned',
            'planned_start_at' => now(),
        ]);

        $this->invokeApplyContract($mission, $batch);

        $mission->refresh();
        // Guard at provider_organization_id keeps the mission untouched.
        $this->assertNull($mission->organization_contract_id);
        $this->assertNull($mission->provider_organization_id);
        $this->assertNull($mission->sla_response_due_at);
    }

    public function test_apply_contract_stamps_contract_provider_and_arms_sla(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();

        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
            'sla_response_hours' => 4,
            'sla_resolution_hours' => 24,
        ]);

        $workOrder = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => $contract->id,
            'title' => 'WO under contract',
            'reference' => 'WO-FLOW-CONTRACT',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        $batch = MissionBatch::create([
            'organization_account_id' => $clientOrg->id,
            'enterprise_work_order_id' => $workOrder->id,
            'reference' => 'WO-'.$workOrder->reference,
            'name' => 'Batch',
            'status' => 'planned',
        ]);

        $mission = Mission::create([
            'enterprise_work_order_id' => $workOrder->id,
            'mission_batch_id' => $batch->id,
            'status' => 'planned',
            'planned_start_at' => now(),
        ]);

        $this->invokeApplyContract($mission, $batch);

        $mission->refresh();
        $this->assertSame($contract->id, $mission->organization_contract_id);
        $this->assertSame($provider->id, $mission->provider_organization_id);
        $this->assertNotNull($mission->sla_response_due_at);
        $this->assertNotNull($mission->sla_resolution_due_at);

        $this->assertDatabaseHas('contract_sla_events', [
            'mission_id' => $mission->id,
            'organization_contract_id' => $contract->id,
            'kind' => 'response',
        ]);
    }
}
