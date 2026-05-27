<?php

namespace Database\Factories;

use App\Models\ChatThread;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatThreadFactory extends Factory
{
    protected $model = ChatThread::class;

    public function definition(): array
    {
        return [
            'code' => ChatThread::generateCode(),
            'context_type' => fake()->randomElement(['booking', 'mission', 'support']),
            'context_id' => fake()->numberBetween(1, 1000),
            'title' => fake()->optional()->sentence(4),
            'status' => ChatThread::STATUS_ACTIVE,
            'is_archived' => false,
            'last_message_at' => null,
            'last_message_preview' => null,
            'message_count' => 0,
            'flagged_count' => 0,
            'metadata' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ChatThread::STATUS_ARCHIVED,
            'is_archived' => true,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => ChatThread::STATUS_LOCKED]);
    }
}
