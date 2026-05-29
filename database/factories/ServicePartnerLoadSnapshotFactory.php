<?php

namespace Database\Factories;

use App\Models\ServicePartner;
use App\Models\ServicePartnerLoadSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePartnerLoadSnapshotFactory extends Factory
{
    protected $model = ServicePartnerLoadSnapshot::class;

    public function definition(): array
    {
        $capacity = fake()->numberBetween(100, 500);
        $planned = fake()->numberBetween(0, $capacity);

        return [
            'service_partner_id' => fn () => ServicePartner::factory()->create()->id,
            'snapshot_date' => now()->toDateString(),
            'active_missions_count' => fake()->numberBetween(0, 20),
            'planned_segments_count' => fake()->numberBetween(0, 30),
            'planned_minutes' => $planned,
            'daily_capacity' => $capacity,
            'utilization_percent' => $capacity > 0 ? round($planned / $capacity * 100, 2) : 0,
            'metadata' => null,
        ];
    }
}
