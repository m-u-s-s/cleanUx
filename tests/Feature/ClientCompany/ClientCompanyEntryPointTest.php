<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Espace société B2B P1 — l'analytics société, longtemps orpheline, doit être
 * accessible et gardée par le type d'organisation, dans l'espace société.
 */
class ClientCompanyEntryPointTest extends TestCase
{
    use RefreshDatabase;

    private function clientCompanyMember(): User
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $user = User::factory()->entreprise()->create();
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
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_client_company_member_reaches_company_analytics(): void
    {
        $this->actingAs($this->clientCompanyMember())
            ->get(route('client-company.analytics'))
            ->assertOk();
    }

    public function test_company_member_sees_bridge_to_company_space_in_individual_nav(): void
    {
        $this->actingAs($this->clientCompanyMember())
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('Espace entreprise');
    }

    public function test_plain_client_does_not_see_company_bridge(): void
    {
        $client = User::factory()->client()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
            'current_organization_id' => null,
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertDontSee('Espace entreprise');
    }
}
