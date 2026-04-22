<?php

namespace Tests\Feature;

use App\Livewire\Admin\B2BOperationsCenter;
use App\Models\Country;
use App\Models\FieldTeam;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\ServiceCatalog;
use App\Models\ServicePartner;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminB2BOperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_b2b_operations_center(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-entreprises'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.b2b.operations'))
            ->assertOk()
            ->assertSee('Centre opérations entreprises');
    }

    public function test_admin_can_create_contract_and_work_order_with_lines(): void
    {
        $country = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $country->id]);
        $service = ServiceCatalog::factory()->create(['is_active' => true]);
        $account = OrganizationAccount::factory()->create(['country_id' => $country->id]);
        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $account->id,
            'service_zone_id' => $zone->id,
        ]);
        $requester = User::factory()->entreprise()->create([
            'organization_account_id' => $account->id,
        ]);
        $lead = User::factory()->employe()->create();
        $team = FieldTeam::create([
            'country_id' => $country->id,
            'service_zone_id' => $zone->id,
            'organization_account_id' => $account->id,
            'team_lead_user_id' => $lead->id,
            'name' => 'Crew Key Account',
            'slug' => 'crew-key-account',
            'status' => 'active',
            'is_internal' => true,
            'max_concurrent_missions' => 4,
        ]);
        $partner = ServicePartner::create([
            'country_id' => $country->id,
            'name' => 'External Ops',
            'legal_name' => 'External Ops SRL',
            'slug' => 'external-ops',
            'status' => 'active',
            'is_active' => true,
        ]);
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-entreprises'],
        ]);

        $this->actingAs($admin);

        Livewire::test(B2BOperationsCenter::class)
            ->set('selectedAccountId', $account->id)
            ->set('contractForm.organization_account_id', $account->id)
            ->set('contractForm.country_id', $country->id)
            ->set('contractForm.service_zone_id', $zone->id)
            ->set('contractForm.default_field_team_id', $team->id)
            ->set('contractForm.default_service_partner_id', $partner->id)
            ->set('contractForm.contract_reference', 'CTR-KA-001')
            ->set('contractForm.status', 'active')
            ->set('contractForm.pricing_model', 'negotiated')
            ->set('contractForm.billing_cycle', 'monthly')
            ->set('contractForm.approval_mode', 'account_owner')
            ->set('contractForm.requires_purchase_order', true)
            ->set('contractForm.default_cost_center', 'CC-001')
            ->call('saveContract')
            ->assertHasNoErrors()
            ->set('workOrderForm.organization_account_id', $account->id)
            ->set('workOrderForm.organization_site_id', $site->id)
            ->set('workOrderForm.organization_contract_id', 1)
            ->set('workOrderForm.service_catalog_id', $service->id)
            ->set('workOrderForm.service_zone_id', $zone->id)
            ->set('workOrderForm.requested_by_user_id', $requester->id)
            ->set('workOrderForm.assigned_field_team_id', $team->id)
            ->set('workOrderForm.assigned_service_partner_id', $partner->id)
            ->set('workOrderForm.title', 'Programme chantier bloc A')
            ->set('workOrderForm.reference', 'WO-KA-001')
            ->set('workOrderForm.status', 'draft')
            ->set('workOrderForm.priority', 'urgente')
            ->set('workOrderForm.approval_status', 'pending')
            ->set('workOrderForm.work_type', 'chantier')
            ->set('workOrderForm.purchase_order_number', 'PO-7788')
            ->set('workOrderForm.cost_center', 'CC-001')
            ->set('workOrderForm.budget_amount', 2450)
            ->set('workOrderLines.0.title', 'Lot 1 nettoyage initial')
            ->set('workOrderLines.0.quantity', 2)
            ->set('workOrderLines.0.unit', 'passages')
            ->set('workOrderLines.0.unit_price', 450)
            ->call('saveWorkOrder')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organization_contracts', [
            'contract_reference' => 'CTR-KA-001',
            'organization_account_id' => $account->id,
        ]);

        $this->assertDatabaseHas('enterprise_work_orders', [
            'reference' => 'WO-KA-001',
            'organization_account_id' => $account->id,
            'assigned_field_team_id' => $team->id,
            'assigned_service_partner_id' => $partner->id,
        ]);

        $this->assertDatabaseHas('work_order_lines', [
            'title' => 'Lot 1 nettoyage initial',
            'unit' => 'passages',
        ]);
    }
}
