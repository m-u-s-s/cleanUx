<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatMessageReadFactory extends Factory
{
    protected $model = ChatMessageRead::class;

    public function definition(): array
    {
        return [
            'message_id' => fn () => ChatMessage::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'read_at' => now(),
        ];
    }
}
