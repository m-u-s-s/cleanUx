<?php

namespace Tests\Feature\Relations;

use App\Livewire\ClientCompany\ClientContractsCenter;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientContractsDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_a_contract_shows_its_detail_and_respects_org_isolation(): void
    {
        $org = OrganizationAccount::factory()->create();
        $foreign = OrganizationAccount::factory()->create();
        $mine = OrganizationContract::factory()->create([
            'organization_account_id' => $org->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active', 'contract_reference' => 'CT-MINE-1',
        ]);
        $foreignContract = OrganizationContract::factory()->create([
            'organization_account_id' => $foreign->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active', 'contract_reference' => 'CT-FOREIGN-9',
        ]);

        $user = User::factory()->create(['current_organization_id' => $org->id]);
        OrganizationMember::create(['organization_account_id' => $org->id, 'user_id' => $user->id, 'role' => 'viewer', 'status' => 'active']);

        $component = Livewire::actingAs($user)->test(ClientContractsCenter::class)
            ->call('viewContract', $mine->id)
            ->assertSet('selectedContractId', $mine->id)
            ->assertSee('CT-MINE-1');

        // Isolation : on ne peut pas ouvrir le contrat d'une autre org.
        $component->call('viewContract', $foreignContract->id)
            ->assertSet('selectedContractId', null);
    }
}
