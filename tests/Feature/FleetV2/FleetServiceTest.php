<?php

namespace Tests\Feature\FleetV2;

use App\Models\FleetAssignment;
use App\Models\FleetEquipment;
use App\Models\FleetMaintenanceLog;
use App\Models\FleetVehicle;
use App\Models\User;
use App\Services\FleetV2\FleetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FleetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('fleet_v2.vehicle_types', ['van', 'truck', 'car']);
        Config::set('fleet_v2.equipment_types', ['tool', 'machine', 'consumable', 'protection']);
        Config::set('fleet_v2.maintenance_types', ['preventive', 'corrective', 'inspection']);
        Config::set('fleet_v2.damage_conditions', ['ok', 'damaged', 'lost', 'needs_maintenance']);
        Config::set('fleet_v2.auto_update_status_on_assign', true);
        Config::set('fleet_v2.auto_schedule_maintenance_on_damage', true);
        Config::set('fleet_v2.block_assignment_on_expired_cert', true);
        Config::set('fleet_v2.default_maintenance_interval_days', ['van' => 365, 'truck' => 180, 'car' => 365]);
    }

    private function vehicle(array $overrides = []): FleetVehicle
    {
        return FleetVehicle::query()->create(array_merge([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-TST-' . random_int(100, 999),
            'vehicle_type' => 'van',
            'fuel_type' => 'diesel',
            'status' => FleetVehicle::STATUS_AVAILABLE,
            'odometer_km' => 10000,
        ], $overrides));
    }

    private function equipment(array $overrides = []): FleetEquipment
    {
        return FleetEquipment::query()->create(array_merge([
            'code' => FleetEquipment::generateCode(),
            'name' => 'Test eq',
            'equipment_type' => 'tool',
            'status' => FleetEquipment::STATUS_AVAILABLE,
        ], $overrides));
    }

    public function test_assign_vehicle_creates_active_assignment_and_marks_in_use(): void
    {
        $v = $this->vehicle();
        $p = User::factory()->create();
        $a = app(FleetService::class)->assignVehicle($v, $p);

        $this->assertSame(FleetAssignment::STATUS_ACTIVE, $a->status);
        $this->assertSame($v->id, $a->vehicle_id);
        $this->assertSame(FleetVehicle::STATUS_IN_USE, $v->fresh()->status);
        $this->assertSame($p->id, $v->fresh()->current_provider_id);
    }

    public function test_assign_vehicle_rejects_when_already_in_use(): void
    {
        $v = $this->vehicle();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        app(FleetService::class)->assignVehicle($v, $p1);

        $this->expectException(ValidationException::class);
        app(FleetService::class)->assignVehicle($v, $p2);
    }

    public function test_assign_vehicle_idempotent_same_provider_same_booking(): void
    {
        $v = $this->vehicle();
        $p = User::factory()->create();
        $svc = app(FleetService::class);
        $a = $svc->assignVehicle($v, $p, bookingId: 42);
        $b = $svc->assignVehicle($v, $p, bookingId: 42);
        $this->assertSame($a->id, $b->id);
    }

    public function test_assign_vehicle_rejects_when_insurance_expired(): void
    {
        $v = $this->vehicle(['insurance_expires_at' => now()->subDay()->toDateString()]);
        $p = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(FleetService::class)->assignVehicle($v, $p);
    }

    public function test_assign_equipment_marks_in_use(): void
    {
        $e = $this->equipment();
        $p = User::factory()->create();
        $a = app(FleetService::class)->assignEquipment($e, $p);

        $this->assertSame(FleetAssignment::STATUS_ACTIVE, $a->status);
        $this->assertSame(FleetEquipment::STATUS_IN_USE, $e->fresh()->status);
    }

    public function test_return_assignment_ok_resets_to_available(): void
    {
        $v = $this->vehicle();
        $p = User::factory()->create();
        $a = app(FleetService::class)->assignVehicle($v, $p);
        $svc = app(FleetService::class);

        $returned = $svc->returnAssignment($a, FleetAssignment::CONDITION_OK);

        $this->assertSame(FleetAssignment::STATUS_COMPLETED, $returned->status);
        $this->assertSame('ok', $returned->returned_condition);
        $this->assertSame(FleetVehicle::STATUS_AVAILABLE, $v->fresh()->status);
        $this->assertNull($v->fresh()->current_provider_id);
    }

    public function test_return_assignment_damaged_pushes_to_maintenance_and_schedules_log(): void
    {
        $v = $this->vehicle();
        $p = User::factory()->create();
        $a = app(FleetService::class)->assignVehicle($v, $p);
        app(FleetService::class)->returnAssignment($a, FleetAssignment::CONDITION_DAMAGED, 'Aile gauche enfoncée');

        $this->assertSame(FleetVehicle::STATUS_MAINTENANCE, $v->fresh()->status);
        $this->assertSame(1, FleetMaintenanceLog::query()->where('vehicle_id', $v->id)->count());
        $log = FleetMaintenanceLog::query()->where('vehicle_id', $v->id)->first();
        $this->assertSame(FleetMaintenanceLog::TYPE_CORRECTIVE, $log->maintenance_type);
    }

    public function test_return_assignment_lost_marks_retired_for_vehicle_and_lost_for_equipment(): void
    {
        $v = $this->vehicle();
        $e = $this->equipment();
        $p = User::factory()->create();
        $svc = app(FleetService::class);
        $av = $svc->assignVehicle($v, $p);
        $ae = $svc->assignEquipment($e, $p);

        $svc->returnAssignment($av, FleetAssignment::CONDITION_LOST);
        $svc->returnAssignment($ae, FleetAssignment::CONDITION_LOST);

        $this->assertSame(FleetVehicle::STATUS_RETIRED, $v->fresh()->status);
        $this->assertSame(FleetEquipment::STATUS_LOST, $e->fresh()->status);
    }

    public function test_return_updates_vehicle_odometer_when_end_km_given(): void
    {
        $v = $this->vehicle(['odometer_km' => 10000]);
        $p = User::factory()->create();
        $a = app(FleetService::class)->assignVehicle($v, $p);
        app(FleetService::class)->returnAssignment($a, FleetAssignment::CONDITION_OK, null, 10350);

        $this->assertSame(10350, $v->fresh()->odometer_km);
    }

    public function test_return_rejects_invalid_condition(): void
    {
        $v = $this->vehicle();
        $p = User::factory()->create();
        $a = app(FleetService::class)->assignVehicle($v, $p);
        $this->expectException(ValidationException::class);
        app(FleetService::class)->returnAssignment($a, 'forbidden');
    }

    public function test_log_maintenance_persists_with_next_due_for_vehicle(): void
    {
        $v = $this->vehicle();
        $admin = User::factory()->admin()->create();
        $log = app(FleetService::class)->logMaintenance(
            $v, null, FleetMaintenanceLog::TYPE_PREVENTIVE,
            null, 12500, 'Vidange + révision', $admin,
        );

        $this->assertSame($v->id, $log->vehicle_id);
        $this->assertNotNull($log->next_due_at);
        $this->assertTrue($log->next_due_at->isFuture());
    }
}
