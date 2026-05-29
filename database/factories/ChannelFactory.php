<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'organization_account_id' => null,
            'mission_id' => null,
            'booking_id' => null,
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement([
                Channel::TYPE_TEAM,
                Channel::TYPE_SUPPORT,
                Channel::TYPE_PRIVATE,
            ]),
            'is_private' => false,
            'is_locked' => false,
            'is_archived' => false,
            'archived_at' => null,
            'archived_by' => null,
            'created_by' => User::factory(),
            'settings' => null,
        ];
    }

    public function team(): static
    {
        return $this->state(['type' => Channel::TYPE_TEAM]);
    }

    public function archived(): static
    {
        return $this->state([
            'is_archived' => true,
            'archived_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
