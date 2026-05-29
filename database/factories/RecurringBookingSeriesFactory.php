<?php

namespace Database\Factories;

use App\Models\RecurringBookingSeries;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringBookingSeries>
 */
class RecurringBookingSeriesFactory extends Factory
{
    protected $model = RecurringBookingSeries::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+30 days');

        return [
            'customer_user_id' => User::factory()->client(),
            'customer_organization_id' => null,
            'organization_site_id' => null,
            'service_catalog_id' => ServiceCatalog::factory(),
            'service_zone_id' => ServiceZone::factory(),
            'frequency' => fake()->randomElement(['weekly', 'biweekly', 'monthly']),
            'interval' => 1,
            'days' => ['monday', 'friday'],
            'starts_at' => $startsAt->format('Y-m-d'),
            'ends_at' => null,
            'occurrence_count' => null,
            'status' => RecurringBookingSeries::STATUS_ACTIVE,
            'timezone' => 'Europe/Brussels',
            'next_occurrence_at' => $startsAt,
            'last_generated_at' => null,
            'metadata' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state(['status' => RecurringBookingSeries::STATUS_PAUSED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => RecurringBookingSeries::STATUS_CANCELLED]);
    }
}
