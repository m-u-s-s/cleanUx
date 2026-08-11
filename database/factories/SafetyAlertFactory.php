<?php

namespace Database\Factories;

use App\Models\SafetyAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafetyAlert>
 */
class SafetyAlertFactory extends Factory
{
    protected $model = SafetyAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'level' => SafetyAlert::LEVEL_EMERGENCY,
            'status' => SafetyAlert::STATUS_OPEN,
            'lat' => 50.8466,
            'lng' => 4.3528,
        ];
    }

    /** « Je ne me sens pas à l'aise, gardez un œil. » */
    public function veille(): static
    {
        return $this->state(fn () => ['level' => SafetyAlert::LEVEL_CHECK_IN]);
    }
}
