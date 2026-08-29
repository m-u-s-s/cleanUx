<?php

namespace Database\Factories;

use App\Models\PeerVehicle;
use App\Models\PeerVehicleAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerVehicleAvailability> */
class PeerVehicleAvailabilityFactory extends Factory
{
    protected $model = PeerVehicleAvailability::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'peer_vehicle_id' => PeerVehicle::factory(),
            'starts_on' => now()->addDays(10)->toDateString(),
            'ends_on' => now()->addDays(14)->toDateString(),
            'kind' => PeerVehicleAvailability::FERMEE,
        ];
    }
}
