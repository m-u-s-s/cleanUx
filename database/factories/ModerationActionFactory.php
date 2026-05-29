<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ModerationAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModerationActionFactory extends Factory
{
    protected $model = ModerationAction::class;

    public function definition(): array
    {
        return [
            'actor_user_id' => fn () => User::factory()->create()->id,
            'channel_id' => fn () => Channel::factory()->create()->id,
            'message_id' => null,
            'target_user_id' => null,
            'action_type' => ModerationAction::TYPE_LOCK_CHANNEL,
            'reason' => fake()->sentence(),
            'payload' => null,
        ];
    }

    public function deleteMessage(): static
    {
        return $this->state(fn () => [
            'action_type' => ModerationAction::TYPE_DELETE_MESSAGE,
        ]);
    }
}
