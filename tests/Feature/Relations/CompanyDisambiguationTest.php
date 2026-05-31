<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDisambiguationTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_company_worker_is_not_a_client_company(): void
    {
        $org = OrganizationAccount::factory()->create(['type' => OrganizationType::PROVIDER_COMPANY->value]);
        $user = User::factory()->create(['organization_account_id' => $org->id, 'current_organization_id' => $org->id]);
        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->assertFalse($user->isClientCompany(), 'une société-prestataire ne doit pas être vue comme société-cliente');
        $this->assertTrue($user->isProviderCompanyWorker());
        $this->assertSame('provider-company.dashboard', $user->homeDashboardRoute());
    }

    public function test_client_company_is_still_a_client_company(): void
    {
        $org = OrganizationAccount::factory()->create(['type' => OrganizationType::CLIENT_COMPANY->value]);
        $user = User::factory()->create(['organization_account_id' => $org->id, 'current_organization_id' => $org->id]);

        $this->assertTrue($user->isClientCompany());
        $this->assertSame('client-company.dashboard', $user->homeDashboardRoute());
    }
}
