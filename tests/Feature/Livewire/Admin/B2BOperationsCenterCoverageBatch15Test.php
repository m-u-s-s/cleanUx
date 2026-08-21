<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\B2BOperationsCenter;
use App\Models\ContractSlaEvent;
use App\Models\EnterpriseWorkOrder;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class B2BOperationsCenterCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'permissions' => ['manage-entreprises'],
        ]);
    }

    public function test_mount_prefills_active_contract_and_load_contract(): void
    {
        $account = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $account->id,
            'status' => 'active',
            'default_cost_center' => 'CC-PREFILL',
            'contract_reference' => 'CTR-PREFILL-1',
            'requires_purchase_order' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(B2BOperationsCenter::class)
            ->assertOk()
            ->assertSet('selectedAccountId', $account->id)
            ->assertSet('contractId', $contract->id)
            ->assertSet('contractForm.contract_reference', 'CTR-PREFILL-1')
            ->assertSet('contractForm.organization_account_id', $account->id)
            ->assertSet('workOrderForm.organization_contract_id', $contract->id)
            ->assertSet('workOrderForm.cost_center', 'CC-PREFILL');
    }

    public function test_updated_selected_account_reprefills(): void
    {
        $first = OrganizationAccount::factory()->create();
        $second = OrganizationAccount::factory()->create();
        OrganizationContract::factory()->create([
            'organization_account_id' => $second->id,
            'status' => 'signed',
            'contract_reference' => 'CTR-SECOND',
            'default_cost_center' => 'CC-SECOND',
        ]);

        Livewire::actingAs($this->admin)
            ->test(B2BOperationsCenter::class)
            ->set('selectedAccountId', $second->id)
            ->assertSet('contractForm.contract_reference', 'CTR-SECOND')
            ->assertSet('contractForm.organization_account_id', $second->id);
    }

    public function test_work_order_lines_add_and_remove(): void
    {
        Livewire::actingAs($this->admin)
            ->test(B2BOperationsCenter::class)
            ->call('addWorkOrderLine')
            ->call('addWorkOrderLine')
            ->assertCount('workOrderLines', 3)
            ->call('removeWorkOrderLine', 1)
            ->assertCount('workOrderLines', 2);
    }

    public function test_approve_blocked_when_purchase_order_required(): void
    {
        $account = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $account->id,
            'requires_purchase_order' => true,
        ]);
        $workOrder = EnterpriseWorkOrder::factory()->create([
            'organization_account_id' => $account->id,
            'organization_contract_id' => $contract->id,
            'purchase_order_number' => null,
            'approval_status' => 'pending',
        ]);

        Livewire::actingAs($this->admin)
            ->test(B2BOperationsCenter::class)
            ->call('approveWorkOrder', $workOrder->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('enterprise_work_orders', [
            'id' => $workOrder->id,
            'approval_status' => 'pending',
        ]);
    }

    public function test_approve_and_reject_work_order(): void
    {
        $account = OrganizationAccount::factory()->create();
        $approvable = EnterpriseWorkOrder::factory()->create([
            'organization_account_id' => $account->id,
            'organization_contract_id' => null,
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);
        $rejectable = EnterpriseWorkOrder::factory()->create([
            'organization_account_id' => $account->id,
            'organization_contract_id' => null,
            'approval_status' => 'pending',
        ]);

        Livewire::actingAs($this->admin)
            ->test(B2BOperationsCenter::class)
            ->call('approveWorkOrder', $approvable->id)
            ->assertDispatched('toast')
            ->call('rejectWorkOrder', $rejectable->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('enterprise_work_orders', [
            'id' => $approvable->id,
            'approval_status' => 'approved',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('enterprise_work_orders', [
            'id' => $rejectable->id,
            'approval_status' => 'rejected',
            'status' => 'blocked',
        ]);
        $this->assertDatabaseHas('work_order_approvals', [
            'enterprise_work_order_id' => $approvable->id,
            'approval_status' => 'approved',
        ]);
        $this->assertDatabaseHas('work_order_approvals', [
            'enterprise_work_order_id' => $rejectable->id,
            'approval_status' => 'rejected',
        ]);
    }

    public function test_add_rate_card_updates_grid(): void
    {
        $account = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $account->id,
        ]);
        $service = ServiceCatalog::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(B2BOperationsCenter::class)
            ->call('addRateCard', $contract->id, $service->id, 2599)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('contract_rate_cards', [
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 2599,
            'currency' => 'EUR',
        ]);
    }

    public function test_sla_breaches_and_counts_aggregates(): void
    {
        $contract = OrganizationContract::factory()->create();

        /*
         * De VRAIES missions : `contract_sla_events.mission_id` porte désormais une clé étrangère.
         * Les identifiants 1, 2 et 3 ne désignaient aucune ligne — le test comptait des
         * agrégats sur des événements rattachés à rien.
         */
        [$m1, $m2, $m3] = [Mission::factory()->create(), Mission::factory()->create(), Mission::factory()->create()];

        ContractSlaEvent::factory()->create([
            'mission_id' => $m1->id,
            'organization_contract_id' => $contract->id,
            'kind' => ContractSlaEvent::KIND_RESPONSE,
            'status' => ContractSlaEvent::STATUS_PENDING,
        ]);
        ContractSlaEvent::factory()->create([
            'mission_id' => $m2->id,
            'organization_contract_id' => $contract->id,
            'kind' => ContractSlaEvent::KIND_RESPONSE,
            'status' => ContractSlaEvent::STATUS_BREACHED,
        ]);
        ContractSlaEvent::factory()->create([
            'mission_id' => $m3->id,
            'organization_contract_id' => $contract->id,
            'kind' => ContractSlaEvent::KIND_RESOLUTION,
            'status' => ContractSlaEvent::STATUS_ESCALATED,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(B2BOperationsCenter::class)
            ->assertOk();

        $counts = $component->instance()->slaCounts;
        $this->assertSame(1, $counts['pending']);
        $this->assertSame(1, $counts['breached']);
        $this->assertSame(1, $counts['escalated']);

        $this->assertCount(2, $component->instance()->slaBreaches);
    }
}
