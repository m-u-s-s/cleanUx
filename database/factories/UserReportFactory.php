<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserReportFactory extends Factory
{
    protected $model = UserReport::class;

    public function definition(): array
    {
        return [
            'code' => UserReport::generateCode(),
            'reporter_user_id' => fn () => User::factory()->create()->id,
            'reported_user_id' => fn () => User::factory()->create()->id,
            'category' => fake()->randomElement(array_keys(UserReport::CATEGORIES)),
            'description' => fake()->paragraph(),
            'evidence' => null,
            'status' => UserReport::STATUS_PENDING,
            'reviewed_by_admin_id' => null,
            'admin_notes' => null,
            'reviewed_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => UserReport::STATUS_RESOLVED_ACTION,
            'reviewed_at' => now(),
        ]);
    }
}
