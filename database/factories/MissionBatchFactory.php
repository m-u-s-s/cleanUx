<?php

namespace Database\Factories;

use App\Models\MissionBatch;
use App\Models\OrganizationAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionBatch>
 */
class MissionBatchFactory extends Factory
{
    protected $model = MissionBatch::class;

    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('now', '+30 days');

        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'organization_site_id' => null,
            'enterprise_work_order_id' => null,
            'field_team_id' => null,
            'service_partner_id' => null,
            'name' => 'Batch '.fake()->monthName().' '.fake()->year(),
            'reference' => 'BATCH-'.strtoupper(fake()->unique()->bothify('######')),
            'status' => fake()->randomElement(['draft', 'planned', 'in_progress', 'completed']),
            'batch_type' => fake()->randomElement(['recurring', 'one_time', 'maintenance']),
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => null,
            'default_start_time' => fake()->randomElement(['08:00', '09:00', '10:00']),
            'default_end_time' => fake()->randomElement(['14:00', '16:00', '17:00']),
            'estimated_total_minutes' => fake()->randomElement([60, 120, 180, 240]),
            'estimated_total_cost' => fake()->randomFloat(2, 100, 2000),
            'metadata' => null,
            'notes' => null,
        ];
    }
}
