<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionTaskSegment;
use App\Models\MissionTaskSegmentAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionTaskSegmentAssignmentFactory extends Factory
{
    protected $model = MissionTaskSegmentAssignment::class;

    public function definition(): array
    {
        return [
            'mission_task_segment_id' => fn () => MissionTaskSegment::factory()->create()->id,
            'mission_id'              => fn () => Mission::factory()->create()->id,
            'field_team_id'           => null,
            'user_id'                 => fn () => User::factory()->create()->id,
            'assigned_by_user_id'     => null,
            'assignment_role'         => 'lead',
            'status'                  => 'assigned',
            'planned_minutes'         => fake()->numberBetween(60, 240),
            'actual_minutes'          => null,
            'sequence_order'          => 1,
            'notes'                   => null,
            'started_at'              => null,
            'completed_at'            => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'         => 'completed',
            'actual_minutes' => fake()->numberBetween(60, 240),
            'started_at'     => now()->subHours(2),
            'completed_at'   => now(),
        ]);
    }
}
