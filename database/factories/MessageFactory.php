<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'channel_id'    => fn () => Channel::factory()->create()->id,
            'user_id'       => fn () => User::factory()->create()->id,
            'content'       => fake()->paragraph(),
            'type'          => Message::TYPE_TEXT,
            'parent_id'     => null,
            'metadata'      => null,
            'edited_at'     => null,
            'replies_count' => 0,
            'last_reply_at' => null,
            'deleted_by'    => null,
            'deleted_reason' => null,
            'is_pinned'     => false,
            'pinned_at'     => null,
            'pinned_by'     => null,
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => [
            'is_pinned' => true,
            'pinned_at' => now(),
        ]);
    }

    public function system(): static
    {
        return $this->state(fn () => ['type' => Message::TYPE_SYSTEM]);
    }
}
