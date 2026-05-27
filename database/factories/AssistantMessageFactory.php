<?php

namespace Database\Factories;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantMessageFactory extends Factory
{
    protected $model = AssistantMessage::class;

    public function definition(): array
    {
        return [
            'assistant_conversation_id' => fn () => AssistantConversation::factory()->create()->id,
            'sender_type' => fake()->randomElement([
                AssistantMessage::SENDER_USER,
                AssistantMessage::SENDER_ASSISTANT,
            ]),
            'content' => fake()->paragraph(),
            'metadata' => null,
        ];
    }

    public function fromAssistant(): static
    {
        return $this->state(fn () => [
            'sender_type' => AssistantMessage::SENDER_ASSISTANT,
        ]);
    }

    public function fromUser(): static
    {
        return $this->state(fn () => [
            'sender_type' => AssistantMessage::SENDER_USER,
        ]);
    }
}
