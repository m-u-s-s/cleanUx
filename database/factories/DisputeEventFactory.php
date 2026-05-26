<?php

namespace Database\Factories;

use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisputeEventFactory extends Factory
{
    protected $model = DisputeEvent::class;

    public function definition(): array
    {
        return [
            'complaint_case_id' => ComplaintCase::factory(),
            'type' => fake()->randomElement(['opened', 'message', 'admin_message', 'status_changed', 'resolved']),
            'visibility' => fake()->randomElement(['private', 'client', 'provider', 'all']),
            'author_user_id' => User::factory(),
            'author_role' => fake()->randomElement(['client', 'provider', 'admin', 'system']),
            'body' => fake()->paragraph(),
            'attachments' => [],
            'payload' => [],
        ];
    }
}
