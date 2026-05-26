<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComplaintCaseFactory extends Factory
{
    protected $model = ComplaintCase::class;

    public function definition(): array
    {
        return [
            'reference' => 'DSP-' . fake()->unique()->numerify('######'),
            'booking_id' => Booking::factory(),
            'client_id' => User::factory(),
            'category' => fake()->randomElement(['quality', 'no_show', 'payment', 'damage', 'safety', 'communication', 'other']),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'open',
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'attachments' => [],
            'meta' => [],
            'escalation_level' => 0,
            'auto_resolved' => false,
        ];
    }
}
