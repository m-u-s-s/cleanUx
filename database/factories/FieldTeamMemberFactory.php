<?php

namespace Database\Factories;

use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldTeamMember>
 */
class FieldTeamMemberFactory extends Factory
{
    protected $model = FieldTeamMember::class;

    public function definition(): array
    {
        return [
            'field_team_id' => FieldTeam::factory(),
            'user_id'       => User::factory()->employe(),
            'role_on_team'  => fake()->randomElement(['worker', 'team_lead', 'specialist']),
            'is_team_lead'  => false,
            'is_active'     => true,
            'joined_at'     => now()->subDays(fake()->numberBetween(1, 365)),
            'left_at'       => null,
            'metadata'      => null,
        ];
    }

    public function teamLead(): static
    {
        return $this->state(['is_team_lead' => true, 'role_on_team' => 'team_lead']);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
            'left_at'   => now()->subDays(fake()->numberBetween(1, 90)),
        ]);
    }
}
