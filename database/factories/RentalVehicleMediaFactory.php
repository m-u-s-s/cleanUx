<?php

namespace Database\Factories;

use App\Models\RentalVehicle;
use App\Models\RentalVehicleMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentalVehicleMedia> */
class RentalVehicleMediaFactory extends Factory
{
    protected $model = RentalVehicleMedia::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'rental_vehicle_id' => fn () => RentalVehicle::factory(),
            'type' => RentalVehicleMedia::TYPE_GALERIE,
            'path' => 'rental/'.fake()->uuid().'.jpg',
            'position' => 0,
        ];
    }

    public function rotation(int $position = 0): static
    {
        return $this->state(fn () => ['type' => RentalVehicleMedia::TYPE_ROTATION, 'position' => $position]);
    }

    public function modele3d(): static
    {
        return $this->state(fn () => [
            'type' => RentalVehicleMedia::TYPE_MODELE_3D,
            'path' => 'rental/'.fake()->uuid().'.glb',
        ]);
    }
}
