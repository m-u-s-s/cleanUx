<?php

namespace Database\Factories;

use App\Models\EnterpriseWorkOrder;
use App\Models\OrganizationAccount;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EnterpriseWorkOrder>
 */
class EnterpriseWorkOrderFactory extends Factory
{
    protected $model = EnterpriseWorkOrder::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+14 days');

        return [
            'organization_account_id'     => OrganizationAccount::factory(),
            'organization_site_id'        => null,
            'organization_contract_id'    => null,
            'service_catalog_id'          => ServiceCatalog::factory(),
            'service_zone_id'             => ServiceZone::factory(),
            'requested_by_user_id'        => User::factory(),
            'assigned_field_team_id'      => null,
            'assigned_service_partner_id' => null,
            'title'                       => fake()->sentence(4),
            'reference'                   => 'WO-' . strtoupper(fake()->unique()->bothify('######')),
            'status'                      => fake()->randomElement(['draft', 'submitted', 'approved', 'scheduled']),
            'priority'                    => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'approval_status'             => 'pending',
            'work_type'                   => fake()->randomElement(['cleaning', 'maintenance', 'inspection']),
            'requested_start_at'          => $start,
            'requested_end_at'            => null,
            'scheduled_start_at'          => null,
            'scheduled_end_at'            => null,
            'purchase_order_number'       => null,
            'cost_center'                 => null,
            'budget_amount'               => fake()->optional(0.5)->randomFloat(2, 100, 5000),
            'instructions'               => fake()->optional()->paragraph(),
            'metadata'                    => null,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status'          => 'approved',
            'approval_status' => 'approved',
        ]);
    }
}
