<?php

namespace Database\Factories;

use App\Models\PartnerZoneCoverage;
use App\Models\ServiceCatalog;
use App\Models\ServicePartner;
use App\Models\ServiceZone;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerZoneCoverageFactory extends Factory
{
    protected $model = PartnerZoneCoverage::class;

    public function definition(): array
    {
        return [
            'service_partner_id'  => fn () => ServicePartner::factory()->create()->id,
            'service_zone_id'     => fn () => ServiceZone::factory()->create()->id,
            'service_catalog_id'  => null,
            'coverage_status'     => 'active',
            'priority'            => fake()->numberBetween(1, 10),
            'max_daily_capacity'  => fake()->numberBetween(5, 20),
            'sla_response_hours'  => fake()->randomElement([4, 8, 24, 48]),
            'metadata'            => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['coverage_status' => 'inactive']);
    }
}
