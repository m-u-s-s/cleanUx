<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionIncident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionIncident>
 */
class MissionIncidentFactory extends Factory
{
    protected $model = MissionIncident::class;

    public function definition(): array
    {
        return [
            'mission_id'           => Mission::factory(),
            'reported_by_user_id'  => User::factory(),
            'resolved_by_user_id'  => null,
            'incident_type'        => fake()->randomElement([
                'damage', 'theft', 'injury', 'access_denied', 'material_missing', 'other',
            ]),
            'severity'             => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'status'               => 'open',
            'title'                => fake()->sentence(5),
            'description'          => fake()->paragraph(),
            'resolution_notes'     => null,
            'client_visible'       => false,
            'reported_at'          => now(),
            'resolved_at'          => null,
            'meta'                 => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state([
            'status'           => 'resolved',
            'resolution_notes' => fake()->sentence(),
            'resolved_at'      => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }
}
