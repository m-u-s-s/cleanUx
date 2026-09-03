<?php

namespace Tests\Feature;

use App\Livewire\Admin\RolesEtPermissions;
use App\Models\Booking;
use App\Models\Feedback;
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

        Booking::factory()->create([
            'service_zone_id' => $zoneA->id,
        ]);
        Booking::factory()->create([
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

    /**
     * LES CAPACITES SE REGLENT DEPUIS `/admin/roles-et-permissions`, ET NULLE PART AILLEURS.
     *
     * L'editeur qui vivait dans `GestionUtilisateurs` n'etait rendu par aucune Blade — donc
     * atteignable seulement par `/livewire/update`, et surtout SANS la regle « on ne donne
     * que ce qu'on a » : ce test-ci accordait `manage-audit-logs` a un acteur qui ne l'avait
     * pas, et le tenait pour normal.
     */
    public function test_les_capacites_se_reglent_depuis_l_ecran_des_roles(): void
    {
        $zone = ServiceZone::factory()->create();
        $acteur = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-users', 'perform-critical-admin-actions', 'manage-audit-logs'],
        ]);
        $cible = User::factory()->admin()->create(['is_active' => true]);

        Livewire::actingAs($acteur)
            ->test(RolesEtPermissions::class)
            ->call('editerLAdministrateur', $cible->id)
            ->set('perimetre', 'zone')
            ->set('zoneGeree', $zone->id)
            ->set('capacitesEnPlus', ['manage-users', 'manage-audit-logs'])
            ->call('enregistrerLAdministrateur')
            ->assertSet('erreur', null);

        $cible->refresh();

        $this->assertSame('zone', $cible->access_scope);
        $this->assertSame($zone->id, $cible->managed_service_zone_id);
        $this->assertEqualsCanonicalizing(['manage-users', 'manage-audit-logs'], $cible->permissionList());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'security.admin_capabilities_updated',
            'user_id' => $acteur->id,
        ]);
    }

    /** TEMOIN — la meme saisie SANS detenir la capacite est refusee, et rien n'est ecrit. */
    public function test_temoin_on_n_accorde_pas_une_capacite_qu_on_n_a_pas(): void
    {
        $acteur = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-users', 'perform-critical-admin-actions'],
        ]);
        $cible = User::factory()->admin()->create(['is_active' => true]);

        Livewire::actingAs($acteur)
            ->test(RolesEtPermissions::class)
            ->call('editerLAdministrateur', $cible->id)
            ->set('capacitesEnPlus', ['manage-audit-logs'])
            ->call('enregistrerLAdministrateur');

        $this->assertNotContains('manage-audit-logs', $cible->fresh()->permissionList());
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
            'rendez_vous_id' => Booking::factory()->create(['service_zone_id' => $zoneA->id])->id,
        ]);
        $hiddenFeedback = Feedback::factory()->create([
            'rendez_vous_id' => Booking::factory()->create(['service_zone_id' => $zoneB->id])->id,
        ]);

        $response = $this->actingAs($admin)->get('/admin/feedbacks/export-csv');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString((string) $visibleFeedback->id, $content);
        $this->assertStringNotContainsString((string) $hiddenFeedback->id, $content);
    }
}
