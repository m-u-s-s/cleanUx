<?php

namespace Database\Factories;

use App\Models\PeerVehicle;
use App\Models\PeerVehicleMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerVehicleMedia> */
class PeerVehicleMediaFactory extends Factory
{
    protected $model = PeerVehicleMedia::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'peer_vehicle_id' => PeerVehicle::factory(),
            'path' => 'peer-vehicles/'.$this->faker->uuid().'.jpg',
            'sort_order' => 0,
            'is_cover' => false,
        ];
    }

    public function couverture(): static
    {
        return $this->state(fn (array $attributs): array => ['is_cover' => true]);
    }
}
