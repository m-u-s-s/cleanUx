<?php

namespace Database\Factories;

use App\Models\FleetMaintenanceLog;
use App\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetMaintenanceLogFactory extends Factory
{
    protected $model = FleetMaintenanceLog::class;

    public function definition(): array
    {
        return [
            'vehicle_id'               => FleetVehicle::factory(),
            'equipment_id'             => null,
            'maintenance_type'         => fake()->randomElement([
                FleetMaintenanceLog::TYPE_PREVENTIVE,
                FleetMaintenanceLog::TYPE_CORRECTIVE,
                FleetMaintenanceLog::TYPE_INSPECTION,
            ]),
            'performed_at'             => now()->subDays(fake()->numberBetween(1, 180)),
            'performed_by_user_id'     => User::factory(),
            'provider_name'            => fake()->company(),
            'cost_cents'               => fake()->numberBetween(5000, 100000),
            'currency'                 => 'EUR',
            'next_due_at'              => now()->addMonths(6),
            'odometer_at_service_km'   => fake()->numberBetween(10000, 300000),
            'notes'                    => fake()->optional()->sentence(),
            'metadata'                 => [],
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'next_due_at' => now()->subMonth(),
        ]);
    }
}
