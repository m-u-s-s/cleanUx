<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserBlockFactory extends Factory
{
    protected $model = UserBlock::class;

    public function definition(): array
    {
        return [
            'blocker_user_id' => fn () => User::factory()->create()->id,
            'blocked_user_id' => fn () => User::factory()->create()->id,
            'reason' => fake()->optional()->sentence(),
            'metadata' => null,
        ];
    }
}
