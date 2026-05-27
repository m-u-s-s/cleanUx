<?php

namespace Database\Factories;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatParticipantFactory extends Factory
{
    protected $model = ChatParticipant::class;

    public function definition(): array
    {
        return [
            'thread_id' => fn () => ChatThread::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'role' => fake()->randomElement([
                ChatParticipant::ROLE_CLIENT,
                ChatParticipant::ROLE_PROVIDER,
                ChatParticipant::ROLE_ADMIN,
            ]),
            'is_muted' => false,
            'can_send' => true,
            'joined_at' => now(),
            'last_read_at' => null,
            'last_read_message_id' => null,
            'left_at' => null,
        ];
    }

    public function left(): static
    {
        return $this->state(fn () => ['left_at' => now()]);
    }
}
