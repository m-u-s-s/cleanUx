<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionReinforcementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionReinforcementRequestFactory extends Factory
{
    protected $model = MissionReinforcementRequest::class;

    public function definition(): array
    {
        return [
            'mission_id'             => fn () => Mission::factory()->create()->id,
            'mission_batch_id'       => null,
            'mission_batch_day_id'   => null,
            'mission_task_segment_id' => null,
            'requested_by_user_id'   => fn () => User::factory()->create()->id,
            'field_team_id'          => null,
            'service_partner_id'     => null,
            'status'                 => 'pending',
            'priority'               => 'normal',
            'requested_members'      => fake()->numberBetween(1, 3),
            'requested_minutes'      => fake()->numberBetween(30, 120),
            'reason'                 => fake()->sentence(),
            'resolution_notes'       => null,
            'resolved_by_user_id'    => null,
            'resolved_at'            => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}
