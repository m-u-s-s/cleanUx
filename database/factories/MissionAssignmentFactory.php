<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionAssignmentFactory extends Factory
{
    protected $model = MissionAssignment::class;

    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'user_id' => User::factory()->employe(),
            'role_on_mission' => fake()->randomElement(['lead', 'member']),
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'accepted_at' => null,
            'declined_at' => null,
        ];
    }

    public function lead(): static
    {
        return $this->state(fn () => [
            'role_on_mission' => 'lead',
        ]);
    }

    public function member(): static
    {
        return $this->state(fn () => [
            'role_on_mission' => 'member',
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'assignment_status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function arrived(): static
    {
        return $this->state(fn () => [
            'assignment_status' => 'arrived',
            'accepted_at' => now()->subMinutes(20),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'assignment_status' => 'completed',
            'accepted_at' => now()->subHours(2),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn () => [
            'assignment_status' => 'declined',
            'declined_at' => now(),
        ]);
    }
}