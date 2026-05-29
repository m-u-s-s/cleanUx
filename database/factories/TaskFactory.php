<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'organization_account_id' => null,
            'channel_id' => null,
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => Task::STATUS_TODO,
            'priority' => fake()->randomElement([
                Task::PRIORITY_LOW,
                Task::PRIORITY_MEDIUM,
                Task::PRIORITY_HIGH,
            ]),
            'due_date' => fake()->optional(0.7)->dateTimeBetween('now', '+30 days'),
            'completed_at' => null,
            'metadata' => null,
        ];
    }

    public function done(): static
    {
        return $this->state([
            'status' => Task::STATUS_DONE,
            'completed_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }

    public function urgent(): static
    {
        return $this->state([
            'priority' => Task::PRIORITY_URGENT,
            'status' => Task::STATUS_IN_PROGRESS,
        ]);
    }
}
