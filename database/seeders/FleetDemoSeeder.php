<?php

namespace Database\Seeders;

use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use Illuminate\Database\Seeder;

class FleetDemoSeeder extends Seeder
{
    public function run(): void
    {
        FleetVehicle::query()->updateOrCreate(
            ['plate' => '1-ABC-123'],
            [
                'code' => FleetVehicle::generateCode(),
                'brand' => 'Renault',
                'model' => 'Master',
                'year' => 2022,
                'vehicle_type' => 'van',
                'fuel_type' => 'diesel',
                'capacity_kg' => 1500,
                'capacity_volume_m3' => 13.0,
                'status' => FleetVehicle::STATUS_AVAILABLE,
                'registered_country' => 'BE',
                'registered_at' => '2022-03-15',
                'insurance_expires_at' => now()->addMonths(8)->toDateString(),
                'control_technique_expires_at' => now()->addMonths(4)->toDateString(),
                'odometer_km' => 45_000,
            ],
        );

        FleetVehicle::query()->updateOrCreate(
            ['plate' => '1-XYZ-789'],
            [
                'code' => FleetVehicle::generateCode(),
                'brand' => 'Volkswagen',
                'model' => 'Crafter',
                'year' => 2021,
                'vehicle_type' => 'van',
                'fuel_type' => 'diesel',
                'capacity_kg' => 1800,
                'capacity_volume_m3' => 14.5,
                'status' => FleetVehicle::STATUS_AVAILABLE,
                'registered_country' => 'BE',
                'registered_at' => '2021-09-10',
                'insurance_expires_at' => now()->addMonths(3)->toDateString(),
                'control_technique_expires_at' => now()->addDays(20)->toDateString(),
                'odometer_km' => 72_500,
            ],
        );

        $equipment = [
            [
                'name' => 'Karcher Pro 400', 'equipment_type' => 'machine', 'category' => 'cleaning',
                'brand' => 'Karcher', 'model' => 'Pro 400', 'value_cents' => 89000,
                'serial_number' => 'KCR-2023-001',
            ],
            [
                'name' => 'Aspirateur industriel', 'equipment_type' => 'machine', 'category' => 'cleaning',
                'brand' => 'Numatic', 'model' => 'WV900', 'value_cents' => 55000,
                'serial_number' => 'NUM-2024-A12',
            ],
            [
                'name' => 'Échafaudage roulant 6m', 'equipment_type' => 'tool', 'category' => 'painting',
                'brand' => 'Layher', 'value_cents' => 220000,
            ],
        ];
        foreach ($equipment as $e) {
            FleetEquipment::query()->updateOrCreate(
                ['name' => $e['name']],
                array_merge($e, [
                    'code' => FleetEquipment::generateCode(),
                    'status' => FleetEquipment::STATUS_AVAILABLE,
                    'currency' => 'EUR',
                    'purchased_at' => '2023-01-15',
                ]),
            );
        }
    }
}
