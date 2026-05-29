<?php

namespace Database\Factories;

use App\Models\FieldTeam;
use App\Models\FieldTeamLoadSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldTeamLoadSnapshotFactory extends Factory
{
    protected $model = FieldTeamLoadSnapshot::class;

    public function definition(): array
    {
        $capacity = fake()->numberBetween(100, 600);
        $planned = fake()->numberBetween(0, $capacity);

        return [
            'field_team_id' => fn () => FieldTeam::factory()->create()->id,
            'snapshot_date' => now()->toDateString(),
            'active_missions_count' => fake()->numberBetween(0, 15),
            'planned_segments_count' => fake()->numberBetween(0, 25),
            'planned_minutes' => $planned,
            'assigned_members_count' => fake()->numberBetween(1, 8),
            'capacity_minutes' => $capacity,
            'utilization_percent' => $capacity > 0 ? round($planned / $capacity * 100, 2) : 0,
            'metadata' => null,
        ];
    }
}
