<?php

namespace Database\Factories;

use App\Models\CustomerClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerClaimFactory extends Factory
{
    protected $model = CustomerClaim::class;

    public function definition(): array
    {
        return [
            'client_id'       => fn () => User::factory()->create()->id,
            'rendez_vous_id'  => null,
            'category'        => fake()->randomElement(['quality', 'delay', 'damage', 'billing']),
            'priority'        => fake()->randomElement(['low', 'normal', 'high']),
            'status'          => 'open',
            'title'           => fake()->sentence(),
            'description'     => fake()->paragraph(),
            'attachments'     => null,
            'sla_due_at'      => now()->addDays(3),
            'resolved_at'     => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}
