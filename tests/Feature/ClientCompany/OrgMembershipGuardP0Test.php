<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\ClientCompanyDashboard;
use App\Livewire\ProviderCompany\ProviderDashboard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P0.1 — org-membership guard (EnforcesActiveOrgMembership).
 *
 * current_organization_id is mass-assignable, so a role check alone lets an entreprise user point
 * at an org they don't belong to (horizontal IDOR). The trait must 403 unless the user is an ACTIVE
 * member of their current organisation — on mount and on every action.
 */
class OrgMembershipGuardP0Test extends TestCase
{
    use RefreshDatabase;

    private function member(OrganizationAccount $org, User $user, string $status = 'active'): void
    {
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => $status,
            'permissions' => null,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    #[Test]
    public function active_member_can_access_their_own_org_dashboard(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->entreprise()->create(['current_organization_id' => $org->id]);
        $this->member($org, $user);

        Livewire::actingAs($user)->test(ClientCompanyDashboard::class)->assertOk();
    }

    #[Test]
    public function member_of_org_a_cannot_act_on_org_b_they_do_not_belong_to(): void
    {
        $orgA = OrganizationAccount::factory()->create();
        $orgB = OrganizationAccount::factory()->create();
        $user = User::factory()->entreprise()->create(['current_organization_id' => $orgA->id]);
        $this->member($orgA, $user);

        // Tamper: point current_organization_id at an org the user is NOT a member of.
        $user->forceFill(['current_organization_id' => $orgB->id])->save();

        Livewire::actingAs($user)->test(ClientCompanyDashboard::class)->assertForbidden();
    }

    #[Test]
    public function inactive_member_is_forbidden(): void
    {
        $org = OrganizationAccount::factory()->create();
        $user = User::factory()->entreprise()->create(['current_organization_id' => $org->id]);
        $this->member($org, $user, status: 'inactive');

        Livewire::actingAs($user)->test(ClientCompanyDashboard::class)->assertForbidden();
    }

    #[Test]
    public function provider_company_user_cannot_access_org_they_do_not_belong_to(): void
    {
        $orgA = OrganizationAccount::factory()->create();
        $orgB = OrganizationAccount::factory()->create();
        $user = User::factory()->create(['current_organization_id' => $orgA->id]);
        $this->member($orgA, $user);

        $user->forceFill(['current_organization_id' => $orgB->id])->save();

        Livewire::actingAs($user)->test(ProviderDashboard::class)->assertForbidden();
    }
}
