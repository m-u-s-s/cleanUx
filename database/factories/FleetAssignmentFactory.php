<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\FleetAssignment;
use App\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetAssignmentFactory extends Factory
{
    protected $model = FleetAssignment::class;

    public function definition(): array
    {
        $assignedAt = now()->subDays(fake()->numberBetween(1, 30));

        return [
            'code' => FleetAssignment::generateCode(),
            'vehicle_id' => FleetVehicle::factory(),
            'equipment_id' => null,
            'booking_id' => Booking::factory(),
            'provider_user_id' => User::factory(),
            'status' => FleetAssignment::STATUS_ACTIVE,
            'assigned_at' => $assignedAt,
            'expected_return_at' => $assignedAt->copy()->addHours(8),
            'returned_at' => null,
            'returned_condition' => null,
            'damage_notes' => null,
            'start_odometer_km' => fake()->numberBetween(10000, 200000),
            'end_odometer_km' => null,
            'assigned_by_user_id' => User::factory(),
            'metadata' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => FleetAssignment::STATUS_COMPLETED,
            'returned_at' => now()->subHour(),
            'returned_condition' => FleetAssignment::CONDITION_OK,
            'end_odometer_km' => fake()->numberBetween(200001, 210000),
        ]);
    }

    public function damaged(): static
    {
        return $this->state(fn () => [
            'status' => FleetAssignment::STATUS_COMPLETED,
            'returned_at' => now()->subHour(),
            'returned_condition' => FleetAssignment::CONDITION_DAMAGED,
            'damage_notes' => 'Scratch on left panel.',
        ]);
    }
}
