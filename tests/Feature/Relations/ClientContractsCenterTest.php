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

class ClientContractsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_member_sees_only_their_org_contracts(): void
    {
        $org = OrganizationAccount::factory()->create();
        $otherOrg = OrganizationAccount::factory()->create();

        $mine = OrganizationContract::factory()->create([
            'organization_account_id' => $org->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
        ]);
        $foreign = OrganizationContract::factory()->create([
            'organization_account_id' => $otherOrg->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active',
        ]);

        $user = User::factory()->create(['current_organization_id' => $org->id]);
        OrganizationMember::create([
            'organization_account_id' => $org->id, 'user_id' => $user->id, 'role' => 'viewer', 'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(ClientContractsCenter::class)
            ->assertSee($mine->contract_reference)
            ->assertDontSee($foreign->contract_reference);
    }
}
