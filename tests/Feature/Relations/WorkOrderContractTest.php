<?php

namespace Tests\Feature\Relations;

use App\Exceptions\ContractPolicyException;
use App\Models\ContractRateCard;
use App\Models\EnterpriseWorkOrder;
use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Models\WorkOrderLine;
use App\Services\Contracts\WorkOrderContractService;
use App\Services\Missions\EnterpriseWorkOrderMissionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_lines_uses_contract_rate_card(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
        ]);
        ContractRateCard::create([
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1500,
            'currency' => 'EUR',
        ]);

        $wo = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'title' => 'WO test',
            'reference' => 'WO-TEST-1',
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);
        $line = WorkOrderLine::create([
            'enterprise_work_order_id' => $wo->id,
            'service_catalog_id' => $service->id,
            'quantity' => 3,
            'unit_price' => 25.0,
        ]);

        app(WorkOrderContractService::class)->priceLines($wo->fresh());

        $line->refresh();
        $this->assertSame('15.00', (string) $line->agreed_unit_price); // 1500 cents
        $this->assertSame('45.00', (string) $line->line_total); // 3 × 15
    }

    public function test_price_lines_is_noop_without_contract(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        $wo = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => null,
            'service_catalog_id' => $service->id,
            'title' => 'WO no contract',
            'reference' => 'WO-TEST-NOC',
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);
        $line = WorkOrderLine::create([
            'enterprise_work_order_id' => $wo->id,
            'service_catalog_id' => $service->id,
            'quantity' => 2,
            'unit_price' => 30.0,
            'line_total' => 60.0,
        ]);

        app(WorkOrderContractService::class)->priceLines($wo->fresh());

        $line->refresh();
        $this->assertNull($line->agreed_unit_price);
        $this->assertSame('60.00', (string) $line->line_total);
    }

    public function test_price_lines_is_idempotent_with_rate_card(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
        ]);
        ContractRateCard::create([
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1500,
            'currency' => 'EUR',
        ]);

        $wo = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'title' => 'WO idempotent',
            'reference' => 'WO-TEST-IDEM',
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);
        $line = WorkOrderLine::create([
            'enterprise_work_order_id' => $wo->id,
            'service_catalog_id' => $service->id,
            'quantity' => 3,
            'unit_price' => 25.0,
        ]);

        $service2 = app(WorkOrderContractService::class);
        $service2->priceLines($wo->fresh());
        $service2->priceLines($wo->fresh());

        $line->refresh();
        $this->assertSame('15.00', (string) $line->agreed_unit_price);
        $this->assertSame('45.00', (string) $line->line_total);
    }

    public function test_work_order_cannot_be_approved_without_po_when_required(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'requires_purchase_order' => true,
        ]);

        $wo = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => $contract->id,
            'title' => 'WO needs PO',
            'reference' => 'WO-TEST-PO',
            'status' => 'draft',
            'approval_status' => 'pending',
            'purchase_order_number' => null,
        ]);

        $this->expectException(ContractPolicyException::class);
        app(WorkOrderContractService::class)->assertApprovable($wo->fresh());
    }

    public function test_work_order_with_po_is_approvable_when_required(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'requires_purchase_order' => true,
        ]);

        $wo = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => $contract->id,
            'title' => 'WO with PO',
            'reference' => 'WO-TEST-PO-OK',
            'status' => 'draft',
            'approval_status' => 'pending',
            'purchase_order_number' => 'PO-123',
        ]);

        app(WorkOrderContractService::class)->assertApprovable($wo->fresh());

        $this->assertTrue(true); // no exception thrown
    }

    /** Targeted proof of the generator's contract wiring: exercises the exact production helper applyContractToMission() that materializePendingMissionsForBatch calls after each Mission::create. */
    public function test_generator_stamps_contract_provider_and_sla_on_mission(): void
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

        $wo = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => $contract->id,
            'title' => 'WO missions',
            'reference' => 'WO-TEST-MISS',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        $batch = MissionBatch::create([
            'organization_account_id' => $clientOrg->id,
            'enterprise_work_order_id' => $wo->id,
            'reference' => 'WO-'.$wo->reference,
            'name' => 'Batch',
            'status' => 'planned',
        ]);

        $mission = Mission::create([
            'enterprise_work_order_id' => $wo->id,
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

    public function test_generator_leaves_mission_untouched_without_contract(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();

        $wo = EnterpriseWorkOrder::create([
            'organization_account_id' => $clientOrg->id,
            'organization_contract_id' => null,
            'title' => 'WO no contract',
            'reference' => 'WO-TEST-MISS-NOC',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        $batch = MissionBatch::create([
            'organization_account_id' => $clientOrg->id,
            'enterprise_work_order_id' => $wo->id,
            'reference' => 'WO-'.$wo->reference,
            'name' => 'Batch',
            'status' => 'planned',
        ]);

        $mission = Mission::create([
            'enterprise_work_order_id' => $wo->id,
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

    private function invokeApplyContract(Mission $mission, MissionBatch $batch): void
    {
        $service = app(EnterpriseWorkOrderMissionGeneratorService::class);
        $ref = new \ReflectionMethod($service, 'applyContractToMission');
        $ref->setAccessible(true);
        $ref->invoke($service, $mission, $batch);
    }
}
