<?php

namespace Database\Factories;

use App\Models\MissionBatch;
use App\Models\MissionBatchDay;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionBatchDayFactory extends Factory
{
    protected $model = MissionBatchDay::class;

    public function definition(): array
    {
        return [
            'mission_batch_id'      => fn () => MissionBatch::factory()->create()->id,
            'field_team_id'         => null,
            'service_partner_id'    => null,
            'status'                => 'planned',
            'service_date'          => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'planned_start_at'      => now()->addDays(1)->setTime(8, 0),
            'planned_end_at'        => now()->addDays(1)->setTime(17, 0),
            'target_mission_count'  => fake()->numberBetween(2, 10),
            'metadata'              => null,
            'notes'                 => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
