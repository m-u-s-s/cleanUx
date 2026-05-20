<?php

namespace Tests\Feature\Integration;

use App\Models\FleetAssignment;
use App\Models\FleetCertification;
use App\Models\FleetMaintenanceLog;
use App\Models\FleetVehicle;
use App\Models\User;
use App\Services\FleetV2\CertificationExpiryScanner;
use App\Services\FleetV2\FleetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Test E2E Fleet : provider assigns vehicle → uses it → returns damaged →
 * vehicle pushed to maintenance + auto corrective maintenance log created.
 */
class FleetAssignmentChainTest extends TestCase
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
        Config::set('fleet_v2.auto_schedule_maintenance_on_damage', true);
        Config::set('fleet_v2.expiring_soon_days', 30);
        Config::set('fleet_v2.default_maintenance_interval_days', ['van' => 365]);
    }

    public function test_full_chain_assign_damaged_return_creates_maintenance_log(): void
    {
        $provider = User::factory()->employe()->create();
        $vehicle = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-CHN-001', 'brand' => 'Renault', 'model' => 'Master',
            'vehicle_type' => 'van', 'fuel_type' => 'diesel',
            'status' => FleetVehicle::STATUS_AVAILABLE,
            'odometer_km' => 50_000,
        ]);

        // 1. Provider gets assigned
        $svc = app(FleetService::class);
        $assignment = $svc->assignVehicle(
            $vehicle, $provider,
            bookingId: 101,
            expectedReturnAt: now()->addHours(8),
        );
        $this->assertSame(FleetAssignment::STATUS_ACTIVE, $assignment->status);
        $this->assertSame(FleetVehicle::STATUS_IN_USE, $vehicle->fresh()->status);

        // 2. Provider returns vehicle damaged
        $returned = $svc->returnAssignment(
            $assignment,
            FleetAssignment::CONDITION_DAMAGED,
            'Aile avant gauche enfoncée',
            55_000,
        );
        $this->assertSame(FleetAssignment::STATUS_COMPLETED, $returned->status);
        $this->assertSame('damaged', $returned->returned_condition);

        // 3. Vehicle now in maintenance + odometer updated
        $vehicle->refresh();
        $this->assertSame(FleetVehicle::STATUS_MAINTENANCE, $vehicle->status);
        $this->assertSame(55_000, $vehicle->odometer_km);

        // 4. Auto corrective maintenance log created
        $logs = FleetMaintenanceLog::query()
            ->where('vehicle_id', $vehicle->id)
            ->get();
        $this->assertCount(1, $logs);
        $this->assertSame(FleetMaintenanceLog::TYPE_CORRECTIVE, $logs->first()->maintenance_type);
    }

    public function test_expired_certification_blocks_new_assignment(): void
    {
        $provider = User::factory()->employe()->create();
        $vehicle = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-CRT-X1', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        // Cert expired
        FleetCertification::query()->create([
            'subject_type' => FleetCertification::SUBJECT_VEHICLE,
            'subject_id' => $vehicle->id,
            'certification_type' => 'insurance',
            'expires_at' => now()->subDays(2),
            'status' => FleetCertification::STATUS_EXPIRED,
        ]);

        $this->expectException(ValidationException::class);
        app(FleetService::class)->assignVehicle($vehicle, $provider);
    }

    public function test_scanner_auto_updates_status_then_unblock_after_renewal(): void
    {
        $provider = User::factory()->employe()->create();
        $vehicle = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-RNW-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        $cert = FleetCertification::query()->create([
            'subject_type' => FleetCertification::SUBJECT_VEHICLE,
            'subject_id' => $vehicle->id,
            'certification_type' => 'insurance',
            'expires_at' => now()->subDay(),
            'status' => 'active',
        ]);

        // Scan détecte expiration
        app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->assertSame(FleetCertification::STATUS_EXPIRED, $cert->fresh()->status);

        // Renouvellement
        $cert->update(['expires_at' => now()->addYear()]);
        app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->assertSame(FleetCertification::STATUS_ACTIVE, $cert->fresh()->status);

        // Assignment OK maintenant
        $assignment = app(FleetService::class)->assignVehicle($vehicle, $provider);
        $this->assertSame(FleetAssignment::STATUS_ACTIVE, $assignment->status);
    }

    public function test_lost_vehicle_marks_retired_not_just_maintenance(): void
    {
        $provider = User::factory()->employe()->create();
        $vehicle = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-LST-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);

        $assignment = app(FleetService::class)->assignVehicle($vehicle, $provider);
        app(FleetService::class)->returnAssignment(
            $assignment, FleetAssignment::CONDITION_LOST, 'Vol signalé',
        );

        $this->assertSame(FleetVehicle::STATUS_RETIRED, $vehicle->fresh()->status);
    }
}
