<?php

namespace Database\Factories;

use App\Models\LimiteJournaliere;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LimiteJournaliereFactory extends Factory
{
    protected $model = LimiteJournaliere::class;

    public function definition(): array
    {
        return [
            'user_id'      => fn () => User::factory()->create()->id,
            'date'         => now()->toDateString(),
            'limite'       => fake()->numberBetween(3, 8),
            'verrou_admin' => false,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => ['verrou_admin' => true]);
    }
}
