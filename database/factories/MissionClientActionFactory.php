<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionClientAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionClientActionFactory extends Factory
{
    protected $model = MissionClientAction::class;

    public function definition(): array
    {
        return [
            'mission_id'      => fn () => Mission::factory()->create()->id,
            'client_user_id'  => fn () => User::factory()->create()->id,
            'action_type'     => fake()->randomElement(['approve', 'reject', 'pause', 'request_extra']),
            'status'          => 'pending',
            'message'         => null,
            'meta'            => null,
            'acted_at'        => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status'   => 'approved',
            'acted_at' => now(),
        ]);
    }
}
