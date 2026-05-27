<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionPartnerAssignment;
use App\Models\ServicePartner;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionPartnerAssignmentFactory extends Factory
{
    protected $model = MissionPartnerAssignment::class;

    public function definition(): array
    {
        return [
            'mission_id'             => fn () => Mission::factory()->create()->id,
            'service_partner_id'     => fn () => ServicePartner::factory()->create()->id,
            'assignment_status'      => 'assigned',
            'assigned_at'            => now(),
            'accepted_at'            => null,
            'started_at'             => null,
            'completed_at'           => null,
            'agreed_rate'            => fake()->randomFloat(2, 100, 500),
            'sla_snapshot'           => null,
            'instructions_snapshot'  => null,
            'metadata'               => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'assignment_status' => 'accepted',
            'accepted_at'       => now(),
        ]);
    }
}
