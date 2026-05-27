<?php

namespace Database\Factories;

use App\Models\AvailabilitySlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AvailabilitySlotFactory extends Factory
{
    protected $model = AvailabilitySlot::class;

    public function definition(): array
    {
        return [
            'provider_user_id' => fn () => User::factory()->employe()->create()->id,
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'valid_from' => null,
            'valid_until' => null,
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
