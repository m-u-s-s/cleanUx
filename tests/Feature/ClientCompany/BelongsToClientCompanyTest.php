<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Espace société B2B P1 — détection d'appartenance à une entreprise cliente, condition du pont de navigation vers l'espace société. */
class BelongsToClientCompanyTest extends TestCase
{
    use RefreshDatabase;

    private function userInOrgOfType(?string $type): User
    {
        $orgId = null;
        if ($type !== null) {
            $orgId = OrganizationAccount::factory()->create(['type' => $type])->id;
        }

        return User::factory()->client()->create(['current_organization_id' => $orgId]);
    }

    public function test_true_when_current_org_is_a_client_company(): void
    {
        $user = $this->userInOrgOfType(OrganizationType::CLIENT_COMPANY->value);

        $this->assertTrue($user->belongsToClientCompany());
    }

    public function test_true_for_hybrid_org(): void
    {
        $user = $this->userInOrgOfType(OrganizationType::HYBRID->value);

        $this->assertTrue($user->belongsToClientCompany());
    }

    public function test_false_without_any_org(): void
    {
        $user = $this->userInOrgOfType(null);

        $this->assertFalse($user->belongsToClientCompany());
    }

    public function test_false_for_provider_company_org(): void
    {
        $user = $this->userInOrgOfType(OrganizationType::PROVIDER_COMPANY->value);

        $this->assertFalse($user->belongsToClientCompany());
    }
}
