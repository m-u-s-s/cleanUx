<?php

namespace Database\Factories;

use App\Models\FleetEquipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FleetEquipmentFactory extends Factory
{
    protected $model = FleetEquipment::class;

    public function definition(): array
    {
        return [
            'code' => 'EQP-' . fake()->unique()->numerify('####'),
            'name' => fake()->randomElement(['Aspirateur pro', 'Monobrosse', 'Nettoyeur vapeur', 'Karcher', 'Échelle télescopique']),
            'equipment_type' => fake()->randomElement(['cleaning', 'painting', 'general', 'safety']),
            'category' => fake()->randomElement(['portable', 'heavy', 'vehicle_mounted']),
            'serial_number' => fake()->bothify('SN-########'),
            'brand' => fake()->company(),
            'status' => 'available',
            'current_provider_id' => User::factory(),
            'value_cents' => fake()->numberBetween(5000, 200000),
            'currency' => 'EUR',
            'purchased_at' => fake()->date(),
            'metadata' => [],
        ];
    }
}
