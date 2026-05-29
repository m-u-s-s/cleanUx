<?php

namespace Database\Factories;

use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\MissionTeamAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionTeamAssignment>
 */
class MissionTeamAssignmentFactory extends Factory
{
    protected $model = MissionTeamAssignment::class;

    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'field_team_id' => FieldTeam::factory(),
            'lead_assignment_id' => null,
            'assignment_status' => fake()->randomElement(['assigned', 'accepted', 'in_progress', 'completed']),
            'assigned_at' => now()->subHours(fake()->numberBetween(1, 24)),
            'accepted_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'instructions_snapshot' => null,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'assignment_status' => 'completed',
            'accepted_at' => now()->subHours(6),
            'started_at' => now()->subHours(5),
            'completed_at' => now()->subHours(2),
        ]);
    }
}
