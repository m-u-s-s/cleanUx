<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\ProviderAgency;
use App\Models\ProviderSiteAssignment;
use App\Models\User;
use App\Services\Missions\InternalAutoAssignmentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LOT 6 — LES RÉFÉRENTS DE SITES, ET LES IMPLANTATIONS DE LA SOCIÉTÉ.
 *
 * `provider_site_assignments` existait depuis le 2026-08-07 avec ZÉRO ligne et AUCUN écrivain : la
 * table était prête, la connaissance qu'elle devait porter — qui connaît le code de la porte,
 * l'ascenseur en panne, l'étage à ne pas déranger avant 10 h — n'avait aucun moyen d'y entrer.
 *
 * LES AGENCES SONT UNE AUTRE NOTION QUE LES SITES, et les confondre coûterait cher.
 * `organization_sites` désigne les locaux du CLIENT ; un prestataire ne possède pas les immeubles où
 * il intervient. Ses propres implantations n'avaient aucune existence, si bien qu'une société
 * multi-villes déclarait tout au même endroit.
 *
 * L'ANTI-FUITE EST LE FIL DE CE FICHIER : plusieurs sociétés concurrentes desservent le même
 * immeuble, l'une le nettoyage, l'autre les espaces verts. Chacune y a ses référents, et ne doit
 * jamais voir ceux de l'autre.
 */
class AgencesEtReferentsTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationRole $role = OrganizationRole::WORKER, ?OrganizationAccount $org = null): User
    {
        $org ??= $this->org;

        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    /** Un site que NOUS desservons — la preuve étant une mission qui s'y déroule. */
    private function siteDesservi(?OrganizationAccount $org = null): OrganizationSite
    {
        $site = OrganizationSite::factory()->create();

        Mission::factory()->create([
            'provider_organization_id' => ($org ?? $this->org)->id,
            'organization_site_id' => $site->id,
        ]);

        return $site;
    }

    // ──────────────────────────────────────────────────────
    // Les référents
    // ──────────────────────────────────────────────────────

    public function test_on_nomme_et_retire_un_referent_de_site(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $habitue = $this->membre();
        $site = $this->siteDesservi();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/sites/{$site->id}/referents", [
                'user_id' => $habitue->id,
                'role' => ProviderSiteAssignment::ROLE_LEAD,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('provider_site_assignments', [
            'provider_organization_id' => $this->org->id,
            'organization_site_id' => $site->id,
            'user_id' => $habitue->id,
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/provider/company/sites/{$site->id}/referents/{$habitue->id}")
            ->assertOk();

        $this->assertDatabaseMissing('provider_site_assignments', [
            'organization_site_id' => $site->id,
            'user_id' => $habitue->id,
        ]);
    }

    public function test_on_ne_nomme_personne_sur_un_site_qu_on_ne_dessert_pas(): void
    {
        // Un prestataire ne possède pas les locaux de ses clients : il ne peut y nommer quelqu'un
        // que s'il y intervient réellement — par mission ou par contrat-cadre.
        $owner = $this->membre(OrganizationRole::OWNER);
        $habitue = $this->membre();

        $siteInconnu = OrganizationSite::factory()->create();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/sites/{$siteInconnu->id}/referents", [
                'user_id' => $habitue->id,
            ])
            ->assertNotFound();
    }

    public function test_on_ne_nomme_pas_l_employe_d_une_autre_societe(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);
        $concurrent = $this->membre(OrganizationRole::WORKER, $autreOrg);
        $site = $this->siteDesservi();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/sites/{$site->id}/referents", [
                'user_id' => $concurrent->id,
            ])
            ->assertNotFound();
    }

    public function test_une_societe_ne_voit_jamais_les_referents_d_une_concurrente(): void
    {
        /*
         * L'ANTI-FUITE ENTRE SOCIÉTÉS SUR UN MÊME IMMEUBLE. Le site est légitimement visible des
         * deux côtés — l'une fait le nettoyage, l'autre les espaces verts. La composition de
         * l'équipe adverse, non.
         */
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $site = $this->siteDesservi();
        // La concurrente dessert le MÊME immeuble.
        Mission::factory()->create([
            'provider_organization_id' => $autreOrg->id,
            'organization_site_id' => $site->id,
        ]);

        $leurHabitue = $this->membre(OrganizationRole::WORKER, $autreOrg);
        ProviderSiteAssignment::create([
            'provider_organization_id' => $autreOrg->id,
            'organization_site_id' => $site->id,
            'user_id' => $leurHabitue->id,
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ]);

        $notreOwner = $this->membre(OrganizationRole::OWNER);

        $sites = $this->actingAs($notreOwner, 'sanctum')
            ->getJson('/api/provider/company/sites')
            ->assertOk()
            ->json('data');

        $leNotre = collect($sites)->firstWhere('id', $site->id);

        $this->assertNotNull($leNotre);
        $this->assertSame([], $leNotre['referents'], 'Les référents de la concurrente ne doivent pas apparaître.');
    }

    public function test_le_worker_ne_nomme_pas_de_referent(): void
    {
        $worker = $this->membre();
        $site = $this->siteDesservi();

        $this->actingAs($worker, 'sanctum')
            ->postJson("/api/provider/company/sites/{$site->id}/referents", ['user_id' => $worker->id])
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────
    // L'équipe par défaut d'un site
    // ──────────────────────────────────────────────────────

    public function test_un_site_recoit_une_equipe_par_defaut_puis_la_perd(): void
    {
        // Nommer des PERSONNES ne suffit pas sur un grand immeuble : c'est une équipe entière qui y
        // va, et la désigner personne par personne recommence à chaque changement d'effectif.
        $owner = $this->membre(OrganizationRole::OWNER);
        $site = $this->siteDesservi();
        $equipe = FieldTeam::factory()->create(['organization_account_id' => $this->org->id]);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/provider/company/sites/{$site->id}/default-team", [
                'field_team_id' => $equipe->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.field_team_id', $equipe->id);

        $this->assertDatabaseHas('provider_site_teams', [
            'provider_organization_id' => $this->org->id,
            'organization_site_id' => $site->id,
            'field_team_id' => $equipe->id,
        ]);

        // `null` retire l'équipe : un site peut cesser d'en avoir une.
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/provider/company/sites/{$site->id}/default-team", ['field_team_id' => null])
            ->assertOk();

        $this->assertDatabaseMissing('provider_site_teams', [
            'organization_site_id' => $site->id,
        ]);
    }

    public function test_l_equipe_par_defaut_doit_etre_la_notre(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);
        $site = $this->siteDesservi();
        $equipeAdverse = FieldTeam::factory()->create(['organization_account_id' => $autreOrg->id]);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/provider/company/sites/{$site->id}/default-team", [
                'field_team_id' => $equipeAdverse->id,
            ])
            ->assertNotFound();
    }

    // ──────────────────────────────────────────────────────
    // Les agences
    // ──────────────────────────────────────────────────────

    public function test_la_societe_cree_et_liste_ses_implantations(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/provider/company/agencies', [
                'name' => 'Dépôt Nord',
                'city' => 'Anvers',
            ])
            ->assertCreated();

        $agences = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/provider/company/agencies')
            ->assertOk()
            ->json('data');

        $this->assertSame('Dépôt Nord', $agences[0]['name']);
    }

    public function test_une_societe_ne_voit_pas_les_implantations_d_une_autre(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        ProviderAgency::create([
            'provider_organization_id' => $autreOrg->id,
            'name' => 'Leur dépôt',
            'slug' => 'leur-depot',
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);

        $this->assertSame(
            [],
            $this->actingAs($owner, 'sanctum')->getJson('/api/provider/company/agencies')->json('data'),
        );
    }

    public function test_le_dispatcheur_lit_les_agences_sans_les_modifier(): void
    {
        // Lecture seule : le répartiteur FILTRE par implantation, il ne redessine pas
        // l'organigramme de la société.
        $dispatcheur = $this->membre(OrganizationRole::DISPATCHER);

        $this->actingAs($dispatcheur, 'sanctum')
            ->getJson('/api/provider/company/agencies')
            ->assertOk();

        $this->actingAs($dispatcheur, 'sanctum')
            ->postJson('/api/provider/company/agencies', ['name' => 'Dépôt pirate'])
            ->assertForbidden();
    }

    public function test_on_rattache_puis_detache_une_equipe_d_une_agence(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $equipe = FieldTeam::factory()->create(['organization_account_id' => $this->org->id]);

        $agence = ProviderAgency::create([
            'provider_organization_id' => $this->org->id,
            'name' => 'Dépôt Sud',
            'slug' => 'depot-sud',
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/agencies/{$agence->id}/attach", [
                'field_team_id' => $equipe->id,
            ])
            ->assertOk();

        $this->assertSame($agence->id, $equipe->fresh()->provider_agency_id);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/agencies/{$agence->id}/attach", [
                'field_team_id' => $equipe->id,
                'detach' => true,
            ])
            ->assertOk();

        $this->assertNull($equipe->fresh()->provider_agency_id);
    }

    // ──────────────────────────────────────────────────────
    // Le moteur : le référent gagne, l'agence départage
    // ──────────────────────────────────────────────────────

    public function test_le_moteur_prefere_le_referent_du_site(): void
    {
        // Le critère le plus lourd, et c'est le cœur du sujet : la connaissance d'un site est la
        // plus chère à reconstituer, et la seule que le client remarque.
        $referent = $this->membre();
        $this->membre();

        $site = $this->siteDesservi();

        ProviderSiteAssignment::create([
            'provider_organization_id' => $this->org->id,
            'organization_site_id' => $site->id,
            'user_id' => $referent->id,
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ]);

        $mission = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'organization_site_id' => $site->id,
            'lead_provider_user_id' => null,
            'planned_start_at' => now()->addWeek()->setTime(9, 0),
            'planned_end_at' => now()->addWeek()->setTime(11, 0),
        ]);

        $this->assertSame(
            $referent->id,
            app(InternalAutoAssignmentEngine::class)->choisirPour($mission)['chosen_user_id'],
        );
    }

    public function test_l_agence_departage_a_egalite_par_ailleurs(): void
    {
        /*
         * L'AGENCE DÉPARTAGE SANS DOMINER. Une société multi-villes préfère envoyer quelqu'un du
         * dépôt le plus proche, mais un référent du site reste plus précieux qu'une proximité
         * d'organigramme — d'où un poids modeste, vérifié ici à critères par ailleurs égaux.
         */
        $duDepot = $this->membre();
        $ailleurs = $this->membre();

        $agence = ProviderAgency::create([
            'provider_organization_id' => $this->org->id,
            'name' => 'Dépôt Nord',
            'slug' => 'depot-nord',
            'status' => 'active',
        ]);

        OrganizationMember::where('organization_account_id', $this->org->id)
            ->where('user_id', $duDepot->id)
            ->update(['provider_agency_id' => $agence->id]);

        $mission = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'provider_agency_id' => $agence->id,
            'lead_provider_user_id' => null,
            'planned_start_at' => now()->addWeek()->setTime(9, 0),
            'planned_end_at' => now()->addWeek()->setTime(11, 0),
        ]);

        $choix = app(InternalAutoAssignmentEngine::class)->choisirPour($mission);

        $this->assertSame($duDepot->id, $choix['chosen_user_id']);
        $this->assertContains($ailleurs->id, array_column($choix['candidates'], 'user_id'));
    }

    public function test_une_societe_sans_agence_ne_voit_rien_changer(): void
    {
        // Le rattachement vaut `null` partout : aucun candidat ne prend le point, et le classement
        // est celui d'avant le lot 6.
        $premier = $this->membre();
        $this->membre();

        $mission = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'lead_provider_user_id' => null,
            'planned_start_at' => now()->addWeek()->setTime(9, 0),
            'planned_end_at' => now()->addWeek()->setTime(11, 0),
        ]);

        $choix = app(InternalAutoAssignmentEngine::class)->choisirPour($mission);

        $this->assertSame($premier->id, $choix['chosen_user_id']);

        $fuites = [];

        foreach ($choix['candidates'] as $candidat) {
            if (array_key_exists('agence', $candidat['detail'])) {
                $fuites[] = $candidat['user_id'] ?? $candidat['id'] ?? '?';
            }
        }

        $this->assertSame([], $fuites, 'Ces candidats exposent leur agence alors que le detail ne doit pas la porter.');
    }
}
