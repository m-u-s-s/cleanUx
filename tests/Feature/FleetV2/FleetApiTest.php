<?php

namespace Tests\Feature\FleetV2;

use App\Models\FleetAssignment;
use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetVehicle;
use App\Models\User;
use App\Services\FleetV2\FleetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FleetApiTest extends TestCase
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

    public function test_admin_create_vehicle(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/fleet-v2/vehicles', [
            'plate' => '1-API-001',
            'brand' => 'Renault',
            'model' => 'Master',
            'year' => 2023,
            'vehicle_type' => 'van',
            'fuel_type' => 'diesel',
            'capacity_kg' => 1500,
        ]);
        $response->assertCreated();
        $this->assertSame(1, FleetVehicle::query()->count());
    }

    public function test_admin_create_vehicle_validates_plate_unique(): void
    {
        $admin = User::factory()->admin()->create();
        FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-DUP-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/fleet-v2/vehicles', [
            'plate' => '1-DUP-001', 'vehicle_type' => 'van',
        ])->assertStatus(422);
    }

    public function test_admin_assign_vehicle(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = User::factory()->create();
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-ASN-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/fleet-v2/vehicles/{$v->id}/assign", [
            'provider_user_id' => $provider->id,
        ]);
        $response->assertCreated();
        $this->assertSame(FleetVehicle::STATUS_IN_USE, $v->fresh()->status);
    }

    public function test_provider_can_return_own_assignment(): void
    {
        $provider = User::factory()->create();
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-RTN-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        $a = app(FleetService::class)->assignVehicle($v, $provider);

        Sanctum::actingAs($provider);
        $response = $this->postJson("/api/v2/fleet/assignments/{$a->id}/return", [
            'condition' => 'ok',
        ]);
        $response->assertOk();
        $this->assertSame(FleetVehicle::STATUS_AVAILABLE, $v->fresh()->status);
    }

    public function test_provider_cannot_return_other_user_assignment(): void
    {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-XR-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        $a = app(FleetService::class)->assignVehicle($v, $p1);

        Sanctum::actingAs($p2);
        $this->postJson("/api/v2/fleet/assignments/{$a->id}/return", [
            'condition' => 'ok',
        ])->assertStatus(403);
    }

    public function test_list_my_assignments_returns_own_only(): void
    {
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $v1 = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-L1-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        $v2 = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-L2-002', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);
        app(FleetService::class)->assignVehicle($v1, $p1);
        app(FleetService::class)->assignVehicle($v2, $p2);

        Sanctum::actingAs($p1);
        $response = $this->getJson('/api/v2/fleet/me/assignments');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_find_available_endpoint(): void
    {
        $user = User::factory()->create();
        FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-AV-001', 'vehicle_type' => 'van', 'fuel_type' => 'diesel',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v2/fleet/available?type=vehicle&vehicle_type=van');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_log_maintenance(): void
    {
        $admin = User::factory()->admin()->create();
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-MNT-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/admin/fleet-v2/maintenance', [
            'vehicle_id' => $v->id,
            'maintenance_type' => 'preventive',
            'cost_cents' => 35000,
            'notes' => 'Vidange',
        ]);
        $response->assertCreated();
    }

    public function test_admin_log_maintenance_rejects_without_subject(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/fleet-v2/maintenance', [
            'maintenance_type' => 'preventive',
        ])->assertStatus(422);
    }

    public function test_admin_add_certification_runs_scan_after(): void
    {
        $admin = User::factory()->admin()->create();
        $v = FleetVehicle::query()->create([
            'code' => FleetVehicle::generateCode(),
            'plate' => '1-CC-001', 'vehicle_type' => 'van',
            'status' => FleetVehicle::STATUS_AVAILABLE,
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/admin/fleet-v2/certifications', [
            'subject_type' => 'vehicle',
            'subject_id' => $v->id,
            'certification_type' => 'insurance',
            'reference' => 'POL-12345',
            'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addDays(10)->toDateString(),
        ]);
        $response->assertCreated();
        $this->assertSame(FleetCertification::STATUS_EXPIRING_SOON, $response->json('certification.status'));
    }

    public function test_admin_scan_expiring_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        FleetCertification::query()->create([
            'subject_type' => 'vehicle', 'subject_id' => 1,
            'certification_type' => 'insurance',
            'expires_at' => now()->subDay(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/admin/fleet-v2/certifications/scan-expiring');
        $response->assertOk();
        $this->assertSame(1, (int) $response->json('counts.expired'));
    }

    public function test_unauthenticated_routes_blocked(): void
    {
        $this->postJson('/api/admin/fleet-v2/vehicles', [
            'plate' => 'X', 'vehicle_type' => 'van',
        ])->assertStatus(401);
    }
}
