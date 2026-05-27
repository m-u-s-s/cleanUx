<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionMemberStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionMemberStatusFactory extends Factory
{
    protected $model = MissionMemberStatus::class;

    public function definition(): array
    {
        return [
            'mission_id'               => fn () => Mission::factory()->create()->id,
            'mission_task_segment_id'  => null,
            'segment_assignment_id'    => null,
            'field_team_id'            => null,
            'user_id'                  => fn () => User::factory()->create()->id,
            'status'                   => 'active',
            'readiness_status'         => 'ready',
            'progress_percent'         => 0,
            'minutes_spent'            => 0,
            'is_blocked'               => false,
            'blocking_reason'          => null,
            'last_reported_at'         => now(),
            'notes'                    => null,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'is_blocked'      => true,
            'blocking_reason' => 'Missing equipment',
        ]);
    }
}
