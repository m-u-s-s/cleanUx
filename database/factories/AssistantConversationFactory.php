<?php

namespace Database\Factories;

use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantConversation>
 */
class AssistantConversationFactory extends Factory
{
    protected $model = AssistantConversation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_account_id' => null,
            'context_role' => fake()->randomElement(['client', 'provider', 'admin', 'enterprise']),
            'status' => AssistantConversation::STATUS_OPEN,
            'context_snapshot' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => AssistantConversation::STATUS_CLOSED]);
    }
}
