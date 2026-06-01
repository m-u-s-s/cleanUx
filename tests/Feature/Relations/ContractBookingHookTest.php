<?php

namespace Tests\Feature\Relations;

use App\Exceptions\ContractPolicyException;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\Contracts\ContractBookingHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractBookingHookTest extends TestCase
{
    use RefreshDatabase;

    private function clientUserInOrg(OrganizationAccount $org): User
    {
        $u = User::factory()->create(['current_organization_id' => $org->id]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $u->id,
            'role' => 'requester', // 'member' n'est pas une valeur valide d'OrganizationRole
            'status' => 'active',
        ]);

        return $u;
    }

    public function test_hook_applies_routing_and_pricing_for_contracted_client(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $provider = OrganizationAccount::factory()->create();
        $service = ServiceCatalog::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $provider->id,
            'status' => 'active',
            'negotiated_discount_percent' => 20,
            'allowed_service_catalog_ids' => [$service->id],
        ]);

        $client = $this->clientUserInOrg($clientOrg);

        $data = ['service_catalog_id' => $service->id, 'devis_estime' => 100.0];
        $out = app(ContractBookingHook::class)->apply($client, $data, now()->toDateString());

        $this->assertSame($provider->id, $out['assigned_provider_organization_id']);
        $this->assertSame($contract->id, $out['organization_contract_id']);
        $this->assertSame(80.0, $out['devis_estime']); // 100 - 20%
    }

    public function test_hook_is_noop_without_contract(): void
    {
        $client = User::factory()->create(); // pas d'org
        $data = ['service_catalog_id' => 1, 'devis_estime' => 100.0];

        $out = app(ContractBookingHook::class)->apply($client, $data, now()->toDateString());

        $this->assertSame($data, $out);
    }

    public function test_hook_blocks_when_po_required_and_missing(): void
    {
        $clientOrg = OrganizationAccount::factory()->create();
        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
            'requires_purchase_order' => true,
        ]);
        $client = $this->clientUserInOrg($clientOrg);

        $this->expectException(ContractPolicyException::class);
        app(ContractBookingHook::class)->apply(
            $client,
            ['service_catalog_id' => ServiceCatalog::factory()->create()->id, 'devis_estime' => 100.0],
            now()->toDateString(),
        );
    }
}
