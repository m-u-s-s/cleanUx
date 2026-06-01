<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Booking\EmployeeAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EligibilityOrgFilterTest extends TestCase
{
    use RefreshDatabase;

    private function companyWorker(int $orgId): User
    {
        $u = User::factory()->create(['is_active' => true]);
        ProviderProfile::create([
            'user_id' => $u->id, 'organization_account_id' => $orgId,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active', 'verification_status' => 'verified',
        ]);

        return $u;
    }

    public function test_eligibility_can_be_scoped_to_an_organization(): void
    {
        $orgA = OrganizationAccount::factory()->create();
        $orgB = OrganizationAccount::factory()->create();
        $workerA = $this->companyWorker($orgA->id);
        $workerB = $this->companyWorker($orgB->id);

        $ids = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery(null, 'company', $orgA->id)->pluck('id');

        $this->assertTrue($ids->contains($workerA->id));
        $this->assertFalse($ids->contains($workerB->id));

        // sans filtre org → les deux
        $allIds = app(EmployeeAvailabilityService::class)
            ->eligibleEmployeesQuery(null, 'company')->pluck('id');
        $this->assertTrue($allIds->contains($workerA->id));
        $this->assertTrue($allIds->contains($workerB->id));
    }
}
