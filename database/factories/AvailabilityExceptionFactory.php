<?php

namespace Database\Factories;

use App\Models\AvailabilityException;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AvailabilityExceptionFactory extends Factory
{
    protected $model = AvailabilityException::class;

    public function definition(): array
    {
        return [
            'provider_user_id' => fn () => User::factory()->employe()->create()->id,
            'date' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'exception_type' => AvailabilityException::TYPE_CLOSED,
            'start_time' => null,
            'end_time' => null,
            'reason' => fake()->optional()->sentence(),
            'metadata' => null,
        ];
    }

    public function openOverride(): static
    {
        return $this->state(fn () => [
            'exception_type' => AvailabilityException::TYPE_OPEN_OVERRIDE,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
        ]);
    }
}
