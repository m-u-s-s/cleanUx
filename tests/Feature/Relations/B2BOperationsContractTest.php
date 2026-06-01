<?php

namespace Tests\Feature\Relations;

use App\Livewire\Admin\B2BOperationsCenter;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class B2BOperationsContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_provider_org_and_rate_card_on_contract(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['manage-entreprises'],
        ]);
        $clientOrg = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();

        Livewire::actingAs($admin)
            ->test(B2BOperationsCenter::class)
            ->set('contractForm.organization_account_id', $clientOrg->id)
            ->set('contractForm.provider_organization_id', $provider->id)
            ->set('contractForm.contract_reference', 'CT-SP4-1')
            ->set('contractForm.status', 'active')
            ->call('saveContract')
            ->assertHasNoErrors();

        $contract = OrganizationContract::where('contract_reference', 'CT-SP4-1')->first();
        $this->assertNotNull($contract);
        $this->assertSame($provider->id, $contract->provider_organization_id);

        Livewire::actingAs($admin)
            ->test(B2BOperationsCenter::class)
            ->call('addRateCard', $contract->id, $service->id, 1800)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('contract_rate_cards', [
            'organization_contract_id' => $contract->id,
            'service_catalog_id' => $service->id,
            'negotiated_unit_price_cents' => 1800,
        ]);
    }
}
