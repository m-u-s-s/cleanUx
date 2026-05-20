<?php

namespace Tests\Feature\FleetV2;

use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use App\Models\User;
use App\Services\FleetV2\AvailabilityChecker;
use App\Services\FleetV2\FleetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AvailabilityCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('fleet_v2.vehicle_types', ['van', 'truck', 'car']);
        Config::set('fleet_v2.equipment_types', ['tool', 'machine']);
        Config::set('fleet_v2.maintenance_types', ['preventive', 'corrective', 'inspection']);
        Config::set('fleet_v2.damage_conditions', ['ok', 'damaged', 'lost', 'needs_maintenance']);
        Config::set('fleet_v2.block_assignment_on_expired_cert', true);
        Config::set('fleet_v2.auto_update_status_on_assign', true);
        Config::set('fleet_v2.default_maintenance_interval_days', ['van' => 365]);
    }

    public function test_available_vehicle_can_be_checked(): void
    {
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-AVL-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        $this->assertTrue(app(AvailabilityChecker::class)->isVehicleAvailable($v));
    }

    public function test_vehicle_in_maintenance_not_available(): void
    {
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-MNT-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_MAINTENANCE,
        ]);
        $this->assertFalse(app(AvailabilityChecker::class)->isVehicleAvailable($v));
    }

    public function test_vehicle_with_expired_insurance_not_available(): void
    {
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-EXP-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
            'insurance_expires_at' => now()->subDay()->toDateString(),
        ]);
        $this->assertFalse(app(AvailabilityChecker::class)->isVehicleAvailable($v));
    }

    public function test_vehicle_with_expired_certification_not_available(): void
    {
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-CRT-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        FleetCertification::query()->create([
            'subject_type' => FleetCertification::SUBJECT_VEHICLE,
            'subject_id' => $v->id,
            'certification_type' => 'insurance',
            'expires_at' => now()->subWeek(),
            'status' => FleetCertification::STATUS_EXPIRED,
        ]);
        $this->assertFalse(app(AvailabilityChecker::class)->isVehicleAvailable($v));
    }

    public function test_find_available_vehicles_filters_by_criteria(): void
    {
        FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-AA-001', 'vehicle_type' => 'van', 'fuel_type' => 'diesel',
            'capacity_kg' => 1500, 'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-BB-002', 'vehicle_type' => 'car', 'fuel_type' => 'electric',
            'capacity_kg' => 400, 'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);

        $vans = app(AvailabilityChecker::class)->findAvailableVehicles(['vehicle_type' => 'van']);
        $this->assertCount(1, $vans);
        $this->assertSame('1-AA-001', $vans->first()->plate);

        $bigCapacity = app(AvailabilityChecker::class)->findAvailableVehicles(['min_capacity_kg' => 1000]);
        $this->assertCount(1, $bigCapacity);
    }

    public function test_find_available_excludes_in_use_after_assignment(): void
    {
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-IU-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        $provider = User::factory()->create();
        app(FleetService::class)->assignVehicle($v, $provider);

        $available = app(AvailabilityChecker::class)->findAvailableVehicles();
        $this->assertCount(0, $available);
    }

    public function test_available_equipment_filter_by_category(): void
    {
        FleetEquipment::query()->create([
            'code' => FleetEquipment::generateCode(),
            'name' => 'Karcher', 'equipment_type' => 'machine', 'category' => 'cleaning',
            'status' => FleetEquipment::STATUS_AVAILABLE,
        ]);
        FleetEquipment::query()->create([
            'code' => FleetEquipment::generateCode(),
            'name' => 'Échelle', 'equipment_type' => 'tool', 'category' => 'painting',
            'status' => FleetEquipment::STATUS_AVAILABLE,
        ]);

        $cleaning = app(AvailabilityChecker::class)->findAvailableEquipment(['category' => 'cleaning']);
        $this->assertCount(1, $cleaning);
        $this->assertSame('Karcher', $cleaning->first()->name);
    }
}
