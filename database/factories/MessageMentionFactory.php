<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageMention;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageMentionFactory extends Factory
{
    protected $model = MessageMention::class;

    public function definition(): array
    {
        return [
            'message_id'        => fn () => Message::factory()->create()->id,
            'mentioned_user_id' => fn () => User::factory()->create()->id,
            'mention_type'      => MessageMention::TYPE_USER,
            'start_offset'      => fake()->numberBetween(0, 50),
            'length'            => fake()->numberBetween(5, 20),
            'read_at'           => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
