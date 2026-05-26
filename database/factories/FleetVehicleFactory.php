<?php

namespace Database\Factories;

use App\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetVehicleFactory extends Factory
{
    protected $model = FleetVehicle::class;

    public function definition(): array
    {
        return [
            'code' => 'VEH-' . fake()->unique()->numerify('####'),
            'plate' => fake()->bothify('?-???-###'),
            'brand' => fake()->randomElement(['Renault', 'Peugeot', 'Citroën', 'Volkswagen', 'Mercedes']),
            'model' => fake()->word(),
            'year' => fake()->numberBetween(2018, 2026),
            'vehicle_type' => fake()->randomElement(['van', 'car', 'truck', 'scooter']),
            'fuel_type' => fake()->randomElement(['diesel', 'electric', 'hybrid', 'gasoline']),
            'status' => 'available',
            'current_provider_id' => User::factory(),
            'odometer_km' => fake()->numberBetween(5000, 150000),
            'registered_at' => fake()->date(),
            'insurance_expires_at' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'metadata' => [],
        ];
    }
}
