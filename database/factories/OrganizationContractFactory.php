<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationContract>
 */
class OrganizationContractFactory extends Factory
{
    protected $model = OrganizationContract::class;

    public function definition(): array
    {
        $effectiveFrom = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'country_id' => Country::factory(),
            'service_zone_id' => null,
            'default_field_team_id' => null,
            'default_service_partner_id' => null,
            'contract_reference' => 'CTR-'.strtoupper(fake()->unique()->bothify('######')),
            'status' => 'active',
            'pricing_model' => fake()->randomElement(['fixed', 'hourly', 'per_visit']),
            'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'annual']),
            'effective_from' => $effectiveFrom->format('Y-m-d'),
            'effective_to' => null,
            'approval_mode' => 'auto',
            'requires_purchase_order' => false,
            'default_cost_center' => null,
            'negotiated_discount_percent' => fake()->optional(0.3)->randomFloat(2, 5, 20),
            'payment_terms_days' => fake()->randomElement([15, 30, 45, 60]),
            'sla_response_hours' => 4,
            'sla_resolution_hours' => 24,
            'allowed_service_catalog_ids' => null,
            'metadata' => null,
            'notes' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'status' => 'expired',
            'effective_to' => fake()->dateTimeBetween('-6 months', '-1 day')->format('Y-m-d'),
        ]);
    }
}
