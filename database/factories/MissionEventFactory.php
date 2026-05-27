<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionEvent>
 */
class MissionEventFactory extends Factory
{
    protected $model = MissionEvent::class;

    public function definition(): array
    {
        $types = [
            'status_changed', 'provider_assigned', 'provider_arrived',
            'mission_started', 'mission_completed', 'checklist_submitted',
            'incident_reported', 'comment_added',
        ];

        return [
            'mission_id'     => Mission::factory(),
            'actor_user_id'  => User::factory(),
            'event_type'     => fake()->randomElement($types),
            'title'          => fake()->sentence(5),
            'description'    => fake()->optional()->sentence(),
            'payload'        => null,
            'happened_at'    => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
