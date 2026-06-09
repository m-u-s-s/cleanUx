<?php

namespace Tests\Feature\FleetV2;

use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use App\Models\User;
use App\Services\FleetV2\FleetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetV2ControllerCoverageBatch7Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('fleet_v2.vehicle_types', ['van', 'truck', 'car']);
        Config::set('fleet_v2.fuel_types', ['diesel', 'petrol', 'electric']);
        Config::set('fleet_v2.equipment_types', ['tool', 'machine']);
        Config::set('fleet_v2.equipment_categories', ['cleaning', 'painting']);
        Config::set('fleet_v2.maintenance_types', ['preventive', 'corrective', 'inspection']);
        Config::set('fleet_v2.damage_conditions', ['ok', 'damaged', 'lost', 'needs_maintenance']);
        Config::set('fleet_v2.certification_types', ['insurance', 'control_technique', 'driver_license']);
        Config::set('fleet_v2.block_assignment_on_expired_cert', true);
        Config::set('fleet_v2.auto_update_status_on_assign', true);
        Config::set('fleet_v2.default_maintenance_interval_days', ['van' => 365]);
        Config::set('fleet_v2.expiring_soon_days', 30);
    }

    private function makeVehicle(string $plate, string $type = 'van', string $status = FleetVehicle::STATUS_AVAILABLE): FleetVehicle
    {
        return FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => $plate,
            'vehicle_type' => $type,
            'status' => $status,
        ]);
    }

    private function makeEquipment(string $name, string $type = 'tool', string $category = 'cleaning', string $status = FleetEquipment::STATUS_AVAILABLE): FleetEquipment
    {
        return FleetEquipment::query()->create([
            'code' => FleetEquipment::generateCode(),
            'name' => $name,
            'equipment_type' => $type,
            'category' => $category,
            'status' => $status,
        ]);
    }

    public function test_admin_list_vehicles_filters_by_status_and_type(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makeVehicle('B7-LV-001', 'van', FleetVehicle::STATUS_AVAILABLE);
        $this->makeVehicle('B7-LV-002', 'truck', FleetVehicle::STATUS_MAINTENANCE);

        Sanctum::actingAs($admin);
        $resp = $this->getJson('/api/admin/fleet-v2/vehicles?status=available&vehicle_type=van&limit=10');
        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame('B7-LV-001', $resp->json('data.0.plate'));
    }

    public function test_admin_create_equipment(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $resp = $this->postJson('/api/admin/fleet-v2/equipment', [
            'name' => 'Karcher Pro',
            'equipment_type' => 'machine',
            'category' => 'cleaning',
            'serial_number' => 'SN-001',
            'value_cents' => 50000,
            'currency' => 'EUR',
        ]);
        $resp->assertCreated();
        $this->assertTrue($resp->json('ok'));
        $this->assertSame(1, FleetEquipment::query()->count());
        $this->assertSame(FleetEquipment::STATUS_AVAILABLE, $resp->json('equipment.status'));
    }

    public function test_admin_create_equipment_validates_type(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/fleet-v2/equipment', [
            'name' => 'Bad', 'equipment_type' => 'nonexistent',
        ])->assertStatus(422);
    }

    public function test_admin_list_equipment_filters_by_status_and_category(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makeEquipment('Alpha Mop', 'tool', 'cleaning', FleetEquipment::STATUS_AVAILABLE);
        $this->makeEquipment('Beta Roller', 'tool', 'painting', FleetEquipment::STATUS_AVAILABLE);

        Sanctum::actingAs($admin);
        $resp = $this->getJson('/api/admin/fleet-v2/equipment?status=available&category=cleaning&limit=5');
        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame('Alpha Mop', $resp->json('data.0.name'));
    }

    public function test_admin_assign_equipment(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = User::factory()->create();
        $eq = $this->makeEquipment('Assignable Tool');

        Sanctum::actingAs($admin);
        $resp = $this->postJson("/api/admin/fleet-v2/equipment/{$eq->id}/assign", [
            'provider_user_id' => $provider->id,
            'assigned_at' => now()->toDateTimeString(),
        ]);
        $resp->assertCreated();
        $this->assertTrue($resp->json('ok'));
        $this->assertSame(FleetEquipment::STATUS_IN_USE, $eq->fresh()->status);
    }

    public function test_admin_assign_equipment_validates_missing_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $eq = $this->makeEquipment('Tool No Provider');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/fleet-v2/equipment/{$eq->id}/assign", [])
            ->assertStatus(422);
    }

    public function test_admin_list_assignments_filters_by_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $v1 = $this->makeVehicle('B7-AS-001');
        $v2 = $this->makeVehicle('B7-AS-002');
        app(FleetService::class)->assignVehicle($v1, $p1);
        app(FleetService::class)->assignVehicle($v2, $p2);

        Sanctum::actingAs($admin);
        $resp = $this->getJson("/api/admin/fleet-v2/assignments?status=active&provider_user_id={$p1->id}&limit=20");
        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame($p1->id, $resp->json('data.0.provider_user_id'));
    }

    public function test_admin_list_maintenance_logs(): void
    {
        $admin = User::factory()->admin()->create();
        $v = $this->makeVehicle('B7-ML-001');
        app(FleetService::class)->logMaintenance($v, null, 'preventive', null, 12000, 'Oil', $admin, null);

        Sanctum::actingAs($admin);
        $resp = $this->getJson('/api/admin/fleet-v2/maintenance?limit=10');
        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
    }

    public function test_admin_list_certifications_filters(): void
    {
        $admin = User::factory()->admin()->create();
        FleetCertification::query()->create([
            'subject_type' => 'vehicle', 'subject_id' => 1,
            'certification_type' => 'insurance',
            'expires_at' => now()->addYear(),
            'status' => FleetCertification::STATUS_ACTIVE,
        ]);
        FleetCertification::query()->create([
            'subject_type' => 'equipment', 'subject_id' => 2,
            'certification_type' => 'control_technique',
            'expires_at' => now()->addYear(),
            'status' => FleetCertification::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($admin);
        $resp = $this->getJson('/api/admin/fleet-v2/certifications?subject_type=vehicle&status=active&limit=10');
        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
        $this->assertSame('vehicle', $resp->json('data.0.subject_type'));
    }

    public function test_find_available_equipment_type(): void
    {
        $user = User::factory()->create();
        $this->makeEquipment('Findable Cleaner', 'machine', 'cleaning');

        Sanctum::actingAs($user);
        $resp = $this->getJson('/api/v2/fleet/available?type=equipment&equipment_type=machine&category=cleaning');
        $resp->assertOk();
        $this->assertSame('equipment', $resp->json('type'));
        $this->assertCount(1, $resp->json('data'));
    }

    public function test_return_assignment_validates_condition(): void
    {
        $provider = User::factory()->create();
        $v = $this->makeVehicle('B7-RV-001');
        $a = app(FleetService::class)->assignVehicle($v, $provider);

        Sanctum::actingAs($provider);
        $this->postJson("/api/v2/fleet/assignments/{$a->id}/return", [
            'condition' => 'invalid_condition',
        ])->assertStatus(422);
    }

    public function test_return_assignment_with_odometer_and_notes(): void
    {
        $provider = User::factory()->create();
        $v = $this->makeVehicle('B7-RO-001');
        $a = app(FleetService::class)->assignVehicle($v, $provider);

        Sanctum::actingAs($provider);
        $resp = $this->postJson("/api/v2/fleet/assignments/{$a->id}/return", [
            'condition' => 'needs_maintenance',
            'notes' => 'Brakes squeaking',
            'end_odometer_km' => 54000,
        ]);
        $resp->assertOk();
        $this->assertTrue($resp->json('ok'));
    }
}
