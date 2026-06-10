<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garde de TYPE d'organisation au niveau route (middleware org.type).
 *
 * EnforcesActiveOrgMembership prouve l'appartenance (anti-IDOR), mais pas le TYPE.
 * Ces tests vérifient en HTTP (le middleware ne s'applique qu'à la route, pas via
 * Livewire::test) qu'un membre d'une org cliente ne peut pas atteindre l'espace
 * prestataire et inversement.
 */
class OrgTypeRouteGuardTest extends TestCase
{
    use RefreshDatabase;

    private function linkActiveMember(OrganizationAccount $org, User $user): void
    {
        $user->forceFill([
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ])->save();

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'permissions' => null,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    private function providerCompanyWorker(): User
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::COMPANY_WORKER,
        ]);

        return $user;
    }

    #[Test]
    public function client_company_member_reaches_client_dashboard(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $user = User::factory()->entreprise()->create();
        $this->linkActiveMember($org, $user);

        $this->actingAs($user->fresh())
            ->get(route('client-company.dashboard'))
            ->assertOk();
    }

    #[Test]
    public function provider_company_member_is_forbidden_on_client_dashboard(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $user = $this->providerCompanyWorker();
        $this->linkActiveMember($org, $user);

        $this->actingAs($user->fresh())
            ->get(route('client-company.dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function provider_company_member_reaches_provider_dashboard(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $user = $this->providerCompanyWorker();
        $this->linkActiveMember($org, $user);

        $this->actingAs($user->fresh())
            ->get(route('provider-company.dashboard'))
            ->assertOk();
    }

    #[Test]
    public function client_company_member_is_forbidden_on_provider_dashboard(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $user = User::factory()->entreprise()->create();
        $this->linkActiveMember($org, $user);

        $this->actingAs($user->fresh())
            ->get(route('provider-company.dashboard'))
            ->assertForbidden();
    }
}
