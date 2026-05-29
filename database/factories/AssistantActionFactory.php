<?php

namespace Database\Factories;

use App\Models\AssistantAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantAction>
 */
class AssistantActionFactory extends Factory
{
    protected $model = AssistantAction::class;

    public function definition(): array
    {
        return [
            'assistant_conversation_id' => null,
            'user_id' => User::factory(),
            'action_type' => fake()->randomElement([
                'cancel_booking', 'create_booking', 'resolve_dispute',
            ]),
            'status' => AssistantAction::STATUS_PENDING_CONFIRMATION,
            'payload' => ['booking_id' => fake()->numberBetween(1, 1000)],
            'confirmed_at' => null,
            'executed_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state([
            'status' => AssistantAction::STATUS_CONFIRMED,
            'confirmed_at' => now()->subMinutes(2),
        ]);
    }

    public function executed(): static
    {
        return $this->state([
            'status' => AssistantAction::STATUS_EXECUTED,
            'confirmed_at' => now()->subMinutes(5),
            'executed_at' => now()->subMinutes(4),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => AssistantAction::STATUS_FAILED,
            'payload' => ['failure_reason' => 'Booking not found'],
        ]);
    }
}
