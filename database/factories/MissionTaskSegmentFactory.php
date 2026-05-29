<?php

namespace Database\Factories;

use App\Models\MissionBatch;
use App\Models\MissionTaskSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionTaskSegmentFactory extends Factory
{
    protected $model = MissionTaskSegment::class;

    public function definition(): array
    {
        return [
            'mission_batch_id' => fn () => MissionBatch::factory()->create()->id,
            'mission_batch_day_id' => null,
            'mission_id' => null,
            'field_team_id' => null,
            'service_partner_id' => null,
            'assigned_user_id' => null,
            'status' => 'pending',
            'segment_type' => fake()->randomElement(['cleaning', 'inspection', 'delivery']),
            'title' => fake()->sentence(4),
            'zone_label' => fake()->word(),
            'service_date' => now()->addDays(3)->format('Y-m-d'),
            'planned_start_at' => now()->addDays(3)->setTime(8, 0),
            'planned_end_at' => now()->addDays(3)->setTime(10, 0),
            'estimated_minutes' => fake()->numberBetween(30, 240),
            'crew_size' => fake()->numberBetween(1, 5),
            'sequence' => fake()->numberBetween(1, 10),
            'metadata' => null,
            'notes' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
