<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\DispatchCenter;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 11 — portail partenaire société : un membre d'une org PROVIDER voit, en
 * lecture seule, les contrats-cadres où SON org est provider_organization_id.
 * Isolation stricte : un contrat où l'org n'est pas partenaire n'apparaît pas.
 */
class PartnerContractsViewTest extends TestCase
{
    use RefreshDatabase;

    private function ownerOf(OrganizationAccount $org): User
    {
        $user = User::factory()->create(['current_organization_id' => $org->id]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_partner_sees_contracts_where_it_is_provider(): void
    {
        $providerOrg = OrganizationAccount::factory()->create();
        $clientOrg = OrganizationAccount::factory()->create();

        $contract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $providerOrg->id,
            'status' => 'active',
            'contract_reference' => 'CT-PARTNER-1',
        ]);

        $user = $this->ownerOf($providerOrg);

        Livewire::actingAs($user)
            ->test(DispatchCenter::class)
            ->assertViewHas('partnerContracts', fn ($c) => $c->contains('id', $contract->id))
            ->assertSee('CT-PARTNER-1');
    }

    public function test_isolation_contract_where_org_is_not_partner_is_hidden(): void
    {
        $providerOrg = OrganizationAccount::factory()->create();
        $otherProviderOrg = OrganizationAccount::factory()->create();
        $clientOrg = OrganizationAccount::factory()->create();

        // Contrat où une AUTRE org est partenaire → ne doit PAS apparaître.
        $foreignContract = OrganizationContract::factory()->create([
            'organization_account_id' => $clientOrg->id,
            'provider_organization_id' => $otherProviderOrg->id,
            'status' => 'active',
            'contract_reference' => 'CT-FOREIGN-9',
        ]);

        $user = $this->ownerOf($providerOrg);

        Livewire::actingAs($user)
            ->test(DispatchCenter::class)
            ->assertViewHas('partnerContracts', fn ($c) => ! $c->contains('id', $foreignContract->id))
            ->assertDontSee('CT-FOREIGN-9');
    }
}
