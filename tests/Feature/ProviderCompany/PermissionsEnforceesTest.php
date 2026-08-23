<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\DispatchCenter;
use App\Livewire\ProviderCompany\ProviderDashboard;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LES CLÉS DE PERMISSION DÉCLARÉES QUE PLUS PERSONNE NE CONSULTAIT. */
class PermissionsEnforceesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeAvec(OrganizationRole $role): array
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        // `ProviderDashboard::mount()` exige `isProviderCompanyWorker()`, donc un `ProviderProfile` de type `company_worker`.
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        return [$org, $user];
    }

    // ──────────────────────────────────────────────────────
    // missions.view_all
    // ──────────────────────────────────────────────────────

    #[Test]
    public function le_nettoyeur_ne_voit_pas_les_missions_de_toute_la_societe(): void
    {
        [$org, $nettoyeur] = $this->societeAvec(OrganizationRole::WORKER);

        Mission::factory()->count(3)->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => today()->setTime(12, 0),
        ]);

        Livewire::actingAs($nettoyeur)
            ->test(ProviderDashboard::class)
            ->assertSet('peutToutVoir', false)
            // Les compteurs société sont ceux du PILOTAGE : combien de missions la société a
            // aujourd'hui, combien sont en retard, qui n'a pas Stripe. Rien de cela ne regarde un
            // exécutant, et le lui montrer expose l'activité complète de son employeur.
            ->assertViewHas('kpis', fn (array $kpis) => $kpis['missions_today'] === 0);
    }

    #[Test]
    public function le_dispatcheur_voit_bien_toute_la_societe(): void
    {
        [$org, $dispatcheur] = $this->societeAvec(OrganizationRole::DISPATCHER);

        Mission::factory()->count(3)->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => today()->setTime(12, 0),
        ]);

        Livewire::actingAs($dispatcheur)
            ->test(ProviderDashboard::class)
            ->assertSet('peutToutVoir', true)
            ->assertViewHas('kpis', fn (array $kpis) => $kpis['missions_today'] === 3);
    }

    #[Test]
    public function une_societe_peut_ouvrir_la_vue_globale_a_ses_chefs_d_equipe(): void
    {
        // Tout l'intérêt de la matrice réglable : la règle appartient à la société, pas au dépôt.
        [$org, $nettoyeur] = $this->societeAvec(OrganizationRole::WORKER);

        OrganizationRolePermission::create([
            'organization_account_id' => $org->id,
            'role' => OrganizationRole::WORKER->value,
            'permission' => 'missions.view_all',
            'granted' => true,
        ]);

        Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => today()->setTime(12, 0),
        ]);

        Livewire::actingAs($nettoyeur)
            ->test(ProviderDashboard::class)
            ->assertSet('peutToutVoir', true);
    }

    // ──────────────────────────────────────────────────────
    // missions.assign
    // ──────────────────────────────────────────────────────

    #[Test]
    public function assigner_exige_missions_assign_au_moment_d_agir(): void
    {
        // LE DÉFAUT : `mount()` vérifiait `missions.dispatch`, et rien d'autre ne vérifiait plus rien.
        [$org, $dispatcheur] = $this->societeAvec(OrganizationRole::DISPATCHER);

        OrganizationRolePermission::create([
            'organization_account_id' => $org->id,
            'role' => OrganizationRole::DISPATCHER->value,
            'permission' => 'missions.assign',
            'granted' => false,
        ]);

        $ouvrier = User::factory()->create();
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $ouvrier->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => today()->setTime(12, 0),
        ]);

        Livewire::actingAs($dispatcheur)
            ->test(DispatchCenter::class)
            // L'écran s'ouvre : `missions.dispatch` reste accordé.
            ->assertOk()
            ->call('startAssign', $mission->id)
            ->set('assigneeId', $ouvrier->id)
            ->call('confirmAssign')
            ->assertForbidden();

        $this->assertDatabaseMissing('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $ouvrier->id,
        ]);
    }

    #[Test]
    public function assigner_reste_possible_pour_qui_a_le_droit(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $ouvrier = User::factory()->create();
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $ouvrier->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $mission = Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => today()->setTime(12, 0),
        ]);

        Livewire::actingAs($patron)
            ->test(DispatchCenter::class)
            ->call('startAssign', $mission->id)
            ->set('assigneeId', $ouvrier->id)
            ->call('confirmAssign')
            ->assertOk();

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $ouvrier->id,
        ]);
    }
}
