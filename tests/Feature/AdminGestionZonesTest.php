<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionZones;
use App\Models\EmployeeZoneAssignment;
use App\Models\PostalCode;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class AdminGestionZonesTest extends TestCase
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

    public function test_admin_can_toggle_zone_flags_and_status(): void
    {
        $context = $this->createCoverageContext();
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->call('toggleZoneBookability')
            ->call('toggleZoneVisibility')
            ->call('setZoneStatus', 'paused');

        $context['zone']->refresh();

        $this->assertFalse($context['zone']->is_bookable);
        $this->assertFalse($context['zone']->is_visible);
        $this->assertSame('paused', $context['zone']->status);
    }

    public function test_admin_can_copy_service_rules_from_another_zone(): void
    {
        $context = $this->createCoverageContext();
        $admin = $this->createAdmin();

        $targetPostal = PostalCode::create([
            'country_id' => $context['country']->id,
            'region_id' => $context['region']->id,
            'province_id' => $context['province']->id,
            'commune_id' => $context['commune']->id,
            'code' => '1001',
            'city_name' => 'Bruxelles Test',
            'latitude' => 50.8503,
            'longitude' => 4.3518,
            'is_active' => true,
        ]);

        $targetZone = ServiceZone::factory()->create([
            'country_id' => $context['country']->id,
            'region_id' => $context['region']->id,
            'province_id' => $context['province']->id,
            'commune_id' => $context['commune']->id,
            'coverage_type' => 'postal_code',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
        ]);
        $targetZone->postalCodes()->attach($targetPostal->id, ['is_primary' => true]);

        ZoneServiceRule::updateOrCreate(
            [
                'service_zone_id' => $context['zone']->id,
                'service_catalog_id' => $context['service']->id,
            ],
            [
                'is_enabled' => true,
                'requires_manual_validation' => true,
                'base_price_override' => 149.99,
                'price_multiplier' => 1.35,
                'minimum_notice_hours' => 36,
                'maximum_daily_capacity' => 3,
            ]
        );

        $this->actingAs($admin);

        Livewire::test(GestionZones::class)
            ->call('selectZone', $targetZone->id)
            ->set('copyRulesFromZoneId', (string) $context['zone']->id)
            ->call('copyServiceRulesFromZone');

        $copiedRule = ZoneServiceRule::query()
            ->where('service_zone_id', $targetZone->id)
            ->where('service_catalog_id', $context['service']->id)
            ->firstOrFail();

        $this->assertTrue($copiedRule->is_enabled);
        $this->assertTrue($copiedRule->requires_manual_validation);
        $this->assertSame('149.99', number_format((float) $copiedRule->base_price_override, 2, '.', ''));
        $this->assertSame('1.35', number_format((float) $copiedRule->price_multiplier, 2, '.', ''));
        $this->assertSame(36, $copiedRule->minimum_notice_hours);
        $this->assertSame(3, $copiedRule->maximum_daily_capacity);
    }

    public function test_admin_can_update_and_remove_zone_assignment(): void
    {
        $context = $this->createCoverageContext();
        $employee = User::factory()->employe()->create();
        $admin = $this->createAdmin();

        $assignment = EmployeeZoneAssignment::create([
            'user_id' => $employee->id,
            'service_zone_id' => $context['zone']->id,
            'assignment_type' => 'secondary',
            'coverage_priority' => 20,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'notes' => 'Initial',
        ]);

        $this->actingAs($admin);

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set("assignmentEdits.{$assignment->id}.assignment_type", 'primary')
            ->set("assignmentEdits.{$assignment->id}.coverage_priority", 5)
            ->set("assignmentEdits.{$assignment->id}.notes", 'Priorité renforcée')
            ->call('saveAssignment', $assignment->id);

        $assignment->refresh();

        $this->assertSame('primary', $assignment->assignment_type);
        $this->assertSame(5, $assignment->coverage_priority);
        $this->assertSame('Priorité renforcée', $assignment->notes);

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->call('removeAssignment', $assignment->id);

        $this->assertDatabaseMissing('employee_zone_assignments', [
            'id' => $assignment->id,
        ]);
    }
}
