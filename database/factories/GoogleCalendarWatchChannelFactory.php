<?php

namespace Database\Factories;

use App\Models\GoogleCalendarWatchChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarWatchChannel>
 *
 * Un canal de notification Google Calendar : `channel_id` est l'identifiant que NOUS générons et
 * transmettons à Google, `resource_id` celui qu'il nous renvoie pour la ressource surveillée.
 */
class GoogleCalendarWatchChannelFactory extends Factory
{
    protected $model = GoogleCalendarWatchChannel::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel_id' => fake()->uuid(),
            'resource_id' => fake()->unique()->lexify('????????????????'),
        ];
    }
}
