<?php

namespace Database\Factories;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\ProviderSiteAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderSiteAssignment>
 */
class ProviderSiteAssignmentFactory extends Factory
{
    protected $model = ProviderSiteAssignment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'provider_organization_id' => OrganizationAccount::factory()->providerCompany(),
            'organization_site_id' => OrganizationSite::factory(),
            'user_id' => User::factory(),
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ];
    }

    public function backup(): static
    {
        return $this->state(fn () => ['role' => ProviderSiteAssignment::ROLE_BACKUP]);
    }
}
