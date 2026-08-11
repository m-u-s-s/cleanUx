<?php

namespace Database\Factories;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSiteBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSiteBudget>
 */
class OrganizationSiteBudgetFactory extends Factory
{
    protected $model = OrganizationSiteBudget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            // `null` = toute la société : le premier budget que la plupart posent.
            'organization_site_id' => null,
            'period' => OrganizationSiteBudget::PERIOD_MONTHLY,
            'period_start' => now()->startOfMonth()->toDateString(),
            'limit_cents' => 500000,
            'currency' => 'EUR',
            'alert_threshold_percent' => 80,
        ];
    }
}
