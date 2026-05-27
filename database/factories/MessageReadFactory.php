<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageReadFactory extends Factory
{
    protected $model = MessageRead::class;

    public function definition(): array
    {
        return [
            'message_id' => fn () => Message::factory()->create()->id,
            'user_id'    => fn () => User::factory()->create()->id,
            'read_at'    => now(),
        ];
    }
}
