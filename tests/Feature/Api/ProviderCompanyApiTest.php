<?php

namespace Tests\Feature\Api;

use App\Enums\OrganizationRole;
use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'ESPACE SOCIÉTÉ N'AVAIT AUCUNE API — SEULEMENT DES ÉCRANS WEB.
 *
 * POURQUOI CE FICHIER EXISTE. `routes/api/provider.php` couvre abondamment le prestataire
 * INDIVIDUEL — missions, disponibilités, badges, litiges, portefeuille — et rien de la société :
 * ni membres, ni équipes terrain, ni tâches. Vérifié endpoint par endpoint.
 *
 * Les écrans société étaient donc servis en WebView faute de données à consommer côté natif. Ces
 * points d'entrée sont la condition préalable à des écrans natifs.
 *
 * Chaque test éprouve les deux mêmes exigences, qui sont celles de tout ce programme :
 *   1. la réponse est limitée à l'organisation active de l'appelant ;
 *   2. l'action est gardée par une permission, pas seulement par l'appartenance.
 */
class ProviderCompanyApiTest extends TestCase
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

        return [$org, $user];
    }

    #[Test]
    public function le_compte_tel_que_db_seed_le_produit_atteint_bien_son_espace(): void
    {
        /*
         * LA FORME EXACTE QUE LES SEEDERS ÉCRIVENT, ET ELLE NE PASSAIT PAS.
         *
         * `organisationActive()` lisait `currentOrganization`, donc la seule colonne
         * `current_organization_id`. Aucun seeder de démonstration ne la renseigne : le rattachement
         * se fait par `organization_account_id`. Les cinq écrans société répondaient donc 403 à tout
         * compte semé, quelle que soit la porte d'entrée.
         *
         * Les tests de ce fichier ne le voyaient pas : `societeAvec()` renseigne LES DEUX colonnes,
         * une forme que la production ne produit pas toujours.
         */
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => null,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/provider/company/members')->assertOk();
        $this->getJson('/api/provider/company/field-teams')->assertOk();
        $this->getJson('/api/provider/company/tasks')->assertOk();
    }

    #[Test]
    public function un_contexte_d_organisation_sans_adhesion_active_ne_donne_rien(): void
    {
        // L'ancienne garde servait l'organisation inscrite dans la colonne sans jamais consulter
        // `organization_members`. Elle ne vérifiait donc aucune appartenance.
        $etrangere = OrganizationAccount::factory()->providerCompany()->create();

        $intrus = User::factory()->create([
            'organization_account_id' => $etrangere->id,
            'current_organization_id' => $etrangere->id,
        ]);

        Sanctum::actingAs($intrus, ['*']);

        $this->getJson('/api/provider/company/members')->assertForbidden();
    }

    #[Test]
    public function l_accueil_societe_resume_la_journee(): void
    {
        /*
         * L'écran d'accueil natif de l'espace société. Il lisait jusqu'ici les mêmes points que le
         * reste, ce qui obligeait l'application à faire quatre appels pour afficher cinq chiffres.
         */
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        Mission::factory()->count(2)->create([
            'provider_organization_id' => $org->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/overview')
            ->assertOk()
            ->assertJsonPath('data.organization.id', $org->id)
            ->assertJsonPath('data.kpis.missions_today', 2)
            ->assertJsonPath('data.kpis.members_active', 1);
    }

    #[Test]
    public function l_accueil_ne_compte_pas_les_missions_d_une_autre_societe(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        Mission::factory()->count(4)->create([
            'provider_organization_id' => $concurrente->id,
            'planned_start_at' => now()->addHours(3),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/overview')
            ->assertOk()
            ->assertJsonPath('data.kpis.missions_today', 0);
    }

    #[Test]
    public function les_sites_desservis_sont_servis_en_natif(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create(['name' => 'Tour Madou']);
        Mission::factory()->create([
            'provider_organization_id' => $org->id,
            'organization_site_id' => $site->id,
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/sites')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tour Madou');
    }

    #[Test]
    public function le_site_d_une_concurrente_n_est_jamais_servi(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        $site = OrganizationSite::factory()->create(['name' => 'Chantier Confidentiel']);
        Mission::factory()->create([
            'provider_organization_id' => $concurrente->id,
            'organization_site_id' => $site->id,
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/sites')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Chantier Confidentiel']);
    }

    #[Test]
    public function les_membres_de_la_societe_sont_listes(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $collegue = User::factory()->create(['name' => 'Camille Dupont']);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $collegue->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/members')
            ->assertOk()
            ->assertJsonPath('data.1.name', 'Camille Dupont')
            ->assertJsonPath('data.1.role', OrganizationRole::WORKER->value);
    }

    #[Test]
    public function la_liste_des_membres_ne_deborde_pas_sur_une_autre_societe(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $etranger = User::factory()->create(['name' => 'Employé Concurrent']);
        OrganizationMember::create([
            'organization_account_id' => $autreOrg->id,
            'user_id' => $etranger->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/members')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Employé Concurrent']);
    }

    #[Test]
    public function les_equipes_terrain_sont_listees_et_creables(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        FieldTeam::create([
            'organization_account_id' => $org->id,
            'name' => 'Agence Nord',
            'slug' => 'agence-nord',
            'status' => 'active',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/field-teams')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Agence Nord');

        $this->postJson('/api/provider/company/field-teams', [
            'name' => 'Agence Sud',
            'max_concurrent_missions' => 5,
        ])->assertCreated();

        $this->assertDatabaseHas('field_teams', [
            'organization_account_id' => $org->id,
            'name' => 'Agence Sud',
        ]);
    }

    #[Test]
    public function un_role_sans_droit_ne_cree_pas_d_equipe(): void
    {
        [, $viewer] = $this->societeAvec(OrganizationRole::VIEWER);

        Sanctum::actingAs($viewer, ['*']);

        $this->postJson('/api/provider/company/field-teams', ['name' => 'Interdite'])
            ->assertForbidden();

        $this->assertDatabaseMissing('field_teams', ['name' => 'Interdite']);
    }

    #[Test]
    public function les_taches_sont_listees_et_deplacables(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $tache = Task::create([
            'organization_account_id' => $org->id,
            'created_by' => $patron->id,
            'title' => 'Remplacer un aspirateur',
            'priority' => 'medium',
            'status' => Task::STATUS_TODO,
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/provider/company/tasks')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Remplacer un aspirateur');

        $this->patchJson("/api/provider/company/tasks/{$tache->id}", [
            'status' => Task::STATUS_DONE,
        ])->assertOk();

        $this->assertSame(Task::STATUS_DONE, $tache->fresh()->status);
    }

    #[Test]
    public function on_ne_deplace_pas_la_tache_d_une_autre_societe(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $etrangere = Task::create([
            'organization_account_id' => $autreOrg->id,
            'created_by' => User::factory()->create()->id,
            'title' => 'Tâche concurrente',
            'priority' => 'low',
            'status' => Task::STATUS_TODO,
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->patchJson("/api/provider/company/tasks/{$etrangere->id}", [
            'status' => Task::STATUS_DONE,
        ])->assertNotFound();

        $this->assertSame(Task::STATUS_TODO, $etrangere->fresh()->status);
    }

    #[Test]
    public function un_particulier_sans_organisation_n_atteint_pas_l_api_societe(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson('/api/provider/company/members')->assertForbidden();
    }
}
