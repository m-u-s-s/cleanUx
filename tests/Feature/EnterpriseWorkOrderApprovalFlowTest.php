<?php

namespace Tests\Feature;

use App\Livewire\Admin\B2BOperationsCenter;
use App\Models\Country;
use App\Models\EnterpriseWorkOrder;
use App\Models\OrganizationAccount;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnterpriseWorkOrderApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_work_order_from_b2b_center(): void
    {
        $country = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $country->id]);
        $account = OrganizationAccount::factory()->create(['country_id' => $country->id]);
        $workOrder = EnterpriseWorkOrder::create([
            'organization_account_id' => $account->id,
            'service_zone_id' => $zone->id,
            'title' => 'Nettoyage bureaux Q2',
            'reference' => 'WO-APPROVE-001',
            'status' => 'draft',
            'priority' => 'haute',
            'approval_status' => 'pending',
            'work_type' => 'office_program',
        ]);

        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-entreprises'],
        ]);

        $this->actingAs($admin);

        Livewire::test(B2BOperationsCenter::class)
            ->call('approveWorkOrder', $workOrder->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('enterprise_work_orders', [
            'id' => $workOrder->id,
            'approval_status' => 'approved',
        ]);

        $this->assertDatabaseHas('work_order_approvals', [
            'enterprise_work_order_id' => $workOrder->id,
            'approval_status' => 'approved',
            'approver_user_id' => $admin->id,
        ]);
    }
}
