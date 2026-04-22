<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionUtilisateurs;
use App\Models\Feedback;
use App\Models\RendezVous;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_scoped_admin_exports_only_his_zone_data(): void
    {
        $zoneA = ServiceZone::factory()->create([
            'name' => 'Visible Security Zone',
        ]);
        $zoneB = ServiceZone::factory()->create([
            'name' => 'Hidden Security Zone',
        ]);

        $admin = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $zoneA->id,
            'permissions' => ['manage-users', 'perform-critical-admin-actions'],
        ]);

        RendezVous::factory()->create([
            'service_zone_id' => $zoneA->id,
        ]);
        RendezVous::factory()->create([
            'service_zone_id' => $zoneB->id,
        ]);

        $response = $this->actingAs($admin)->get('/admin/export/csv');

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Visible Security Zone', $content);
        $this->assertStringNotContainsString('Hidden Security Zone', $content);
    }

    public function test_readonly_admin_cannot_export_sensitive_data(): void
    {
        $admin = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_READONLY,
            'permissions' => ['manage-users', 'perform-critical-admin-actions'],
        ]);

        $this->actingAs($admin)
            ->get('/admin/export/csv')
            ->assertForbidden();
    }

    public function test_admin_can_update_admin_security_from_livewire_component(): void
    {
        $zone = ServiceZone::factory()->create();
        $superAdmin = User::factory()->admin()->create([
            'permissions' => ['manage-users', 'perform-critical-admin-actions'],
        ]);
        $target = User::factory()->admin()->create();

        Livewire::actingAs($superAdmin)
            ->test(GestionUtilisateurs::class)
            ->call('editSecurity', $target->id)
            ->set('securityAccessScope', 'zone')
            ->set('securityManagedZoneId', $zone->id)
            ->set('securityPermissions', ['manage-users', 'manage-audit-logs'])
            ->call('saveSecurity')
            ->assertHasNoErrors();

        $target->refresh();

        $this->assertSame('zone', $target->access_scope);
        $this->assertSame($zone->id, $target->managed_service_zone_id);
        $this->assertSame(['manage-users', 'manage-audit-logs'], $target->permissions);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'security.admin_security_updated',
            'user_id' => $superAdmin->id,
        ]);
    }

    public function test_zone_scoped_admin_only_sees_feedback_of_his_zone(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();

        $admin = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ZONE,
            'managed_service_zone_id' => $zoneA->id,
            'permissions' => ['manage-users', 'perform-critical-admin-actions'],
        ]);

        $visibleFeedback = Feedback::factory()->create([
            'rendez_vous_id' => RendezVous::factory()->create(['service_zone_id' => $zoneA->id])->id,
        ]);
        $hiddenFeedback = Feedback::factory()->create([
            'rendez_vous_id' => RendezVous::factory()->create(['service_zone_id' => $zoneB->id])->id,
        ]);

        $response = $this->actingAs($admin)->get('/admin/feedbacks/export-csv');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString((string) $visibleFeedback->id, $content);
        $this->assertStringNotContainsString((string) $hiddenFeedback->id, $content);
    }
}
