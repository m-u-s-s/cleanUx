<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'thread_id' => fn () => ChatThread::factory()->create()->id,
            'sender_user_id' => fn () => User::factory()->create()->id,
            'sender_role' => fake()->randomElement(['client', 'provider', 'admin']),
            'body' => fake()->paragraph(),
            'is_redacted' => false,
            'body_original_hash' => null,
            'is_deleted' => false,
            'deleted_at' => null,
            'deleted_by_user_id' => null,
            'attachment_path' => null,
            'attachment_mime' => null,
            'attachment_size_bytes' => null,
            'moderation_status' => ChatMessage::MODERATION_CLEAN,
            'moderation_reason' => null,
            'metadata' => null,
        ];
    }

    public function flagged(): static
    {
        return $this->state(fn () => ['moderation_status' => ChatMessage::MODERATION_FLAGGED]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['moderation_status' => ChatMessage::MODERATION_BLOCKED]);
    }
}
