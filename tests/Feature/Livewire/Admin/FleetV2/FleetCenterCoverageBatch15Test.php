<?php

namespace Tests\Feature\Livewire\Admin\FleetV2;

use App\Livewire\Admin\FleetV2\FleetCenter;
use App\Models\FleetAssignment;
use App\Models\FleetCertification;
use App\Models\FleetEquipment;
use App\Models\FleetMaintenanceLog;
use App\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FleetCenterCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('fleet_v2.expiring_soon_days', 30);

        $this->admin = User::factory()->admin()->create();
    }

    private function makeEquipment(string $status = FleetEquipment::STATUS_AVAILABLE): FleetEquipment
    {
        return FleetEquipment::query()->create([
            'code' => FleetEquipment::generateCode(),
            'name' => 'Test eq '.Str::random(4),
            'equipment_type' => 'tool',
            'status' => $status,
        ]);
    }

    private function makeAssignment(string $status, FleetVehicle $vehicle): FleetAssignment
    {
        return FleetAssignment::query()->create([
            'code' => FleetAssignment::generateCode(),
            'vehicle_id' => $vehicle->id,
            'equipment_id' => null,
            'booking_id' => null,
            'provider_user_id' => User::factory()->create()->id,
            'status' => $status,
            'assigned_at' => now()->subDay(),
            'expected_return_at' => now()->addDay(),
            'start_odometer_km' => 10000,
            'assigned_by_user_id' => $this->admin->id,
            'metadata' => [],
        ]);
    }

    private function makeCertification(string $status, string $expiresAt): FleetCertification
    {
        $vehicle = FleetVehicle::factory()->create();

        return FleetCertification::query()->create([
            'subject_type' => FleetCertification::SUBJECT_VEHICLE,
            'subject_id' => $vehicle->id,
            'certification_type' => 'insurance',
            'reference' => Str::upper(Str::random(10)),
            'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => $expiresAt,
            'issuing_authority' => 'Authority',
            'document_path' => 'certifications/'.Str::uuid().'.pdf',
            'status' => $status,
            'created_by_user_id' => $this->admin->id,
            'metadata' => [],
        ]);
    }

    public function test_mount_renders_vehicles_tab_with_kpis_and_items(): void
    {
        FleetVehicle::factory()->create(['status' => FleetVehicle::STATUS_AVAILABLE]);
        FleetVehicle::factory()->create(['status' => FleetVehicle::STATUS_IN_USE]);

        Livewire::actingAs($this->admin)
            ->test(FleetCenter::class)
            ->assertOk()
            ->assertSet('tab', 'vehicles')
            ->assertViewHas('kpis')
            ->assertViewHas('items')
            ->assertViewHas('expiringSoonDays', 30);
    }

    public function test_vehicles_tab_applies_status_filter(): void
    {
        FleetVehicle::factory()->create(['status' => FleetVehicle::STATUS_AVAILABLE]);
        FleetVehicle::factory()->create(['status' => FleetVehicle::STATUS_MAINTENANCE]);

        Livewire::actingAs($this->admin)
            ->test(FleetCenter::class)
            ->set('filterStatus', FleetVehicle::STATUS_MAINTENANCE)
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_equipment_tab_renders_with_filter(): void
    {
        $this->makeEquipment(FleetEquipment::STATUS_AVAILABLE);
        $this->makeEquipment(FleetEquipment::STATUS_MAINTENANCE);

        Livewire::actingAs($this->admin)
            ->test(FleetCenter::class)
            ->set('tab', 'equipment')
            ->set('filterStatus', FleetEquipment::STATUS_AVAILABLE)
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_assignments_tab_renders_with_eager_loads_and_filter(): void
    {
        $vehicle = FleetVehicle::factory()->create();
        $this->makeAssignment(FleetAssignment::STATUS_ACTIVE, $vehicle);
        $this->makeAssignment(FleetAssignment::STATUS_COMPLETED, FleetVehicle::factory()->create());

        Livewire::actingAs($this->admin)
            ->test(FleetCenter::class)
            ->set('tab', 'assignments')
            ->set('filterStatus', FleetAssignment::STATUS_ACTIVE)
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_maintenance_tab_renders(): void
    {
        $vehicle = FleetVehicle::factory()->create();
        FleetMaintenanceLog::query()->create([
            'vehicle_id' => $vehicle->id,
            'equipment_id' => null,
            'maintenance_type' => FleetMaintenanceLog::TYPE_PREVENTIVE,
            'performed_at' => now()->subDays(5),
            'performed_by_user_id' => $this->admin->id,
            'provider_name' => 'Garage',
            'cost_cents' => 12000,
            'currency' => 'EUR',
            'next_due_at' => now()->addMonths(6),
            'odometer_at_service_km' => 12000,
            'metadata' => [],
        ]);

        Livewire::actingAs($this->admin)
            ->test(FleetCenter::class)
            ->set('tab', 'maintenance')
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_certifications_tab_renders_with_status_filter(): void
    {
        $this->makeCertification(FleetCertification::STATUS_ACTIVE, now()->addYear()->toDateString());
        $this->makeCertification(FleetCertification::STATUS_EXPIRED, now()->subMonth()->toDateString());

        Livewire::actingAs($this->admin)
            ->test(FleetCenter::class)
            ->set('tab', 'certifications')
            ->set('filterStatus', FleetCertification::STATUS_EXPIRED)
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_scan_expiring_updates_statuses_and_dispatches_toast(): void
    {
        // Active cert whose expiry already passed -> should flip to expired.
        $this->makeCertification(FleetCertification::STATUS_ACTIVE, now()->subMonth()->toDateString());

        Livewire::actingAs($this->admin)
            ->test(FleetCenter::class)
            ->call('scanExpiring')
            ->assertDispatched('toast');

        $this->assertSame(
            1,
            FleetCertification::query()->where('status', FleetCertification::STATUS_EXPIRED)->count(),
        );
    }
}
