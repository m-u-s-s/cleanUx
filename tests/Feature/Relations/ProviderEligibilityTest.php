<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $providerType, string $status = 'active', string $verif = 'verified', ?int $orgId = null): User
    {
        $user = User::factory()->create(['is_active' => true]);
        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $orgId,
            'provider_type' => $providerType,
            'status' => $status,
            'verification_status' => $verif,
        ]);

        return $user;
    }

    public function test_any_type_includes_independent_and_company_worker_but_excludes_unverified(): void
    {
        $indep = $this->makeProvider(ProviderType::INDEPENDENT->value);
        $org = OrganizationAccount::factory()->create();
        $companyWorker = $this->makeProvider(ProviderType::COMPANY_WORKER->value, orgId: $org->id);
        $unverified = $this->makeProvider(ProviderType::INDEPENDENT->value, verif: 'unverified');
        $pending = $this->makeProvider(ProviderType::INDEPENDENT->value, status: 'pending');

        $ids = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery(null, 'any')->pluck('id');

        $this->assertTrue($ids->contains($indep->id));
        $this->assertTrue($ids->contains($companyWorker->id));
        $this->assertFalse($ids->contains($unverified->id), 'un presta non vérifié ne doit pas être éligible');
        $this->assertFalse($ids->contains($pending->id), 'un presta non actif ne doit pas être éligible');
    }

    public function test_independent_type_excludes_company_worker_and_vice_versa(): void
    {
        $indep = $this->makeProvider(ProviderType::INDEPENDENT->value);
        $org = OrganizationAccount::factory()->create();
        $companyWorker = $this->makeProvider(ProviderType::COMPANY_WORKER->value, orgId: $org->id);

        $independentOnly = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery(null, 'independent')->pluck('id');
        $this->assertTrue($independentOnly->contains($indep->id));
        $this->assertFalse($independentOnly->contains($companyWorker->id));

        $companyOnly = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery(null, 'company')->pluck('id');
        $this->assertTrue($companyOnly->contains($companyWorker->id));
        $this->assertFalse($companyOnly->contains($indep->id));
    }
}
