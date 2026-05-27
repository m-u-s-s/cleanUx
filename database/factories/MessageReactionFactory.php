<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageReactionFactory extends Factory
{
    protected $model = MessageReaction::class;

    public function definition(): array
    {
        return [
            'message_id' => fn () => Message::factory()->create()->id,
            'user_id'    => fn () => User::factory()->create()->id,
            'emoji'      => fake()->randomElement(['+1', 'heart', 'rocket', 'tada', 'eyes']),
        ];
    }
}
