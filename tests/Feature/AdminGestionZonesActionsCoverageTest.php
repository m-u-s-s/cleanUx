<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionZones;
use App\Models\EmployeeZoneAssignment;
use App\Models\User;
use App\Models\ZoneServiceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class AdminGestionZonesActionsCoverageTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_save_zone_form(): void
    {
        $context = $this->createCoverageContext();
        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set('name', 'Zone Renommee')
            ->set('status', 'active')
            ->set('coverage_type', 'commune')
            ->set('priority', 42)
            ->set('minimum_notice_hours', 12)
            ->set('maximum_daily_jobs', 8)
            ->set('travel_surcharge', 7.5)
            ->set('time_buffer_minutes', 30)
            ->set('notes', 'Note de zone')
            ->call('saveZone')
            ->assertHasNoErrors();

        $context['zone']->refresh();

        $this->assertSame('Zone Renommee', $context['zone']->name);
        $this->assertSame('commune', $context['zone']->coverage_type);
        $this->assertSame(42, $context['zone']->priority);
        $this->assertSame(12, $context['zone']->minimum_notice_hours);
        $this->assertSame(8, $context['zone']->maximum_daily_jobs);
        $this->assertSame(30, $context['zone']->time_buffer_minutes);
        $this->assertSame('Note de zone', $context['zone']->notes);
    }

    public function test_save_zone_validates_input(): void
    {
        $context = $this->createCoverageContext();
        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set('name', '')
            ->set('priority', 0)
            ->call('saveZone')
            ->assertHasErrors(['name', 'priority']);
    }

    public function test_admin_can_save_single_service_rule(): void
    {
        $context = $this->createCoverageContext();
        $serviceId = $context['service']->id;
        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set("serviceRules.$serviceId.is_enabled", true)
            ->set("serviceRules.$serviceId.requires_manual_validation", true)
            ->set("serviceRules.$serviceId.base_price_override", '120.50')
            ->set("serviceRules.$serviceId.price_multiplier", '1.5')
            ->set("serviceRules.$serviceId.minimum_notice_hours", '48')
            ->set("serviceRules.$serviceId.maximum_daily_capacity", '6')
            ->call('saveServiceRule', $serviceId)
            ->assertHasNoErrors();

        $rule = ZoneServiceRule::query()
            ->where('service_zone_id', $context['zone']->id)
            ->where('service_catalog_id', $serviceId)
            ->firstOrFail();

        $this->assertTrue((bool) $rule->is_enabled);
        $this->assertTrue((bool) $rule->requires_manual_validation);
        $this->assertSame('120.50', number_format((float) $rule->base_price_override, 2, '.', ''));
        $this->assertSame(48, $rule->minimum_notice_hours);
        $this->assertSame(6, $rule->maximum_daily_capacity);
    }

    public function test_admin_can_save_all_service_rules(): void
    {
        $context = $this->createCoverageContext();
        $serviceId = $context['service']->id;
        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set("serviceRules.$serviceId.is_enabled", false)
            ->set("serviceRules.$serviceId.price_multiplier", '2')
            ->call('saveAllServiceRules')
            ->assertHasNoErrors();

        $rule = ZoneServiceRule::query()
            ->where('service_zone_id', $context['zone']->id)
            ->where('service_catalog_id', $serviceId)
            ->firstOrFail();

        $this->assertFalse((bool) $rule->is_enabled);
        $this->assertSame('2.00', number_format((float) $rule->price_multiplier, 2, '.', ''));
    }

    public function test_admin_can_assign_employee_and_demote_existing_primary(): void
    {
        $context = $this->createCoverageContext();
        $existingEmployee = User::factory()->employe()->create();
        $newEmployee = User::factory()->employe()->create();

        $existing = EmployeeZoneAssignment::create([
            'user_id' => $existingEmployee->id,
            'service_zone_id' => $context['zone']->id,
            'assignment_type' => 'primary',
            'coverage_priority' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'notes' => null,
        ]);

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set('employeeToAssign', (string) $newEmployee->id)
            ->set('assignmentType', 'primary')
            ->set('assignmentPriority', 3)
            ->set('assignmentNotes', 'Nouveau principal')
            ->call('assignEmployee')
            ->assertHasNoErrors();

        $existing->refresh();
        $this->assertSame('secondary', $existing->assignment_type);

        $created = EmployeeZoneAssignment::query()
            ->where('service_zone_id', $context['zone']->id)
            ->where('user_id', $newEmployee->id)
            ->firstOrFail();

        $this->assertSame('primary', $created->assignment_type);
        $this->assertSame(3, $created->coverage_priority);
        $this->assertTrue((bool) $created->is_active);
    }

    public function test_admin_can_toggle_assignment_active_state(): void
    {
        $context = $this->createCoverageContext();
        $employee = User::factory()->employe()->create();

        $assignment = EmployeeZoneAssignment::create([
            'user_id' => $employee->id,
            'service_zone_id' => $context['zone']->id,
            'assignment_type' => 'secondary',
            'coverage_priority' => 10,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'notes' => null,
        ]);

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->call('toggleAssignment', $assignment->id);

        $assignment->refresh();
        $this->assertFalse((bool) $assignment->is_active);
        $this->assertNotNull($assignment->ends_at);

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->call('toggleAssignment', $assignment->id);

        $assignment->refresh();
        $this->assertTrue((bool) $assignment->is_active);
    }

    public function test_set_zone_status_aborts_on_invalid_status(): void
    {
        $context = $this->createCoverageContext();
        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->call('setZoneStatus', 'bogus')
            ->assertStatus(422);
    }
}
