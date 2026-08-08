<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Missions\MissionAssignmentService;
use App\Services\Missions\ReassignmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LOT 3 — UNE ÉQUIPE CRÉÉE DANS L'ESPACE SOCIÉTÉ NE POUVAIT RECEVOIR AUCUNE MISSION.
 *
 * Trois notions d'équipe coexistaient : `provider_teams` (cible de la FK `missions.provider_team_id`,
 * sans modèle Eloquent, alimentée par les seuls seeders), `field_teams` (modèles complets, créées
 * par l'espace société, JAMAIS référencées par `missions`), et un vestige Jetstream. Une société qui
 * créait son « Équipe Nord » sur son propre écran ne pouvait donc rien lui confier.
 *
 * ET DEUX « CHEFS D'ÉQUIPE » QUI S'IGNORAIENT : `OrganizationRole::TEAM_LEAD`, un rang dans la
 * société qui ne dit pas QUELLE équipe, et `field_teams.team_lead_user_id`, la personne qui mène une
 * équipe précise sans rang particulier. Prendre l'une pour l'autre donne deux erreurs opposées —
 * un chef d'équipe réassignant les missions de toute la société, ou le meneur d'une équipe incapable
 * de toucher aux siennes.
 */
class AssignationEquipeTest extends TestCase
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

    private function membre(OrganizationRole $role, ?OrganizationAccount $org = null): User
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

    /**
     * @param  list<User>  $membres
     */
    private function equipe(array $membres, ?User $chef = null, ?OrganizationAccount $org = null): FieldTeam
    {
        $org ??= $this->org;

        $equipe = FieldTeam::factory()->create([
            'organization_account_id' => $org->id,
            'team_lead_user_id' => $chef?->id,
            'status' => 'active',
        ]);

        foreach ($membres as $membre) {
            FieldTeamMember::create([
                'field_team_id' => $equipe->id,
                'user_id' => $membre->id,
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }

        return $equipe->fresh();
    }

    private function mission(?OrganizationAccount $org = null): Mission
    {
        return Mission::factory()->create([
            'provider_organization_id' => ($org ?? $this->org)->id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Assigner une équipe entière
    // ──────────────────────────────────────────────────────

    public function test_une_equipe_de_trois_donne_un_responsable_et_deux_renforts(): void
    {
        $chef = $this->membre(OrganizationRole::TEAM_LEAD);
        $a = $this->membre(OrganizationRole::WORKER);
        $b = $this->membre(OrganizationRole::WORKER);

        $equipe = $this->equipe([$chef, $a, $b], chef: $chef);
        $mission = $this->mission();

        $this->assertTrue(app(MissionAssignmentService::class)->assignerEquipe($mission, $equipe));

        $mission->refresh();

        $this->assertSame($equipe->id, $mission->field_team_id, 'La mission doit porter son équipe.');
        $this->assertSame($chef->id, $mission->lead_provider_user_id);

        $lignes = MissionAssignment::where('mission_id', $mission->id)
            ->where('assignment_status', 'assigned')
            ->pluck('role_on_mission', 'user_id');

        $this->assertSame('lead', $lignes[$chef->id]);
        $this->assertSame('helper', $lignes[$a->id]);
        $this->assertSame('helper', $lignes[$b->id]);
    }

    public function test_sans_chef_declare_le_premier_membre_actif_prend_la_tete(): void
    {
        /*
         * Une mission sans responsable n'est pas assignée du tout : `lead_provider_user_id` est lu
         * par le tableau de bord, l'autorisation Reverb `mission.{id}` et le suivi de trajet.
         */
        $a = $this->membre(OrganizationRole::WORKER);
        $b = $this->membre(OrganizationRole::WORKER);

        $equipe = $this->equipe([$a, $b]);
        $mission = $this->mission();

        app(MissionAssignmentService::class)->assignerEquipe($mission, $equipe);

        $this->assertSame($a->id, $mission->fresh()->lead_provider_user_id);
    }

    public function test_un_chef_qui_a_quitte_l_equipe_ne_prend_pas_la_tete(): void
    {
        /*
         * `team_lead_user_id` peut désigner quelqu'un qui n'est plus membre actif : la colonne n'est
         * pas mise à jour par tous les chemins. Le retenir aveuglément assignerait la mission à un
         * absent, et le répartiteur la croirait couverte.
         */
        $ancienChef = $this->membre(OrganizationRole::TEAM_LEAD);
        $reste = $this->membre(OrganizationRole::WORKER);

        $equipe = $this->equipe([$ancienChef, $reste], chef: $ancienChef);

        FieldTeamMember::where('field_team_id', $equipe->id)
            ->where('user_id', $ancienChef->id)
            ->update(['is_active' => false, 'left_at' => now()]);

        $mission = $this->mission();
        app(MissionAssignmentService::class)->assignerEquipe($mission, $equipe->fresh());

        $this->assertSame($reste->id, $mission->fresh()->lead_provider_user_id);
    }

    public function test_l_equipe_d_une_autre_societe_est_refusee(): void
    {
        // L'anti-fuite : `missions.field_team_id` n'a pas de contrainte SQL, et une clé étrangère ne
        // saurait de toute façon pas exprimer « la même société que la mission ».
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $etranger = $this->membre(OrganizationRole::WORKER, $autreOrg);
        $equipeAdverse = $this->equipe([$etranger], org: $autreOrg);

        $mission = $this->mission();

        $this->assertFalse(app(MissionAssignmentService::class)->assignerEquipe($mission, $equipeAdverse));
        $this->assertNull($mission->fresh()->field_team_id);
        $this->assertNull($mission->fresh()->lead_provider_user_id);
    }

    public function test_une_equipe_sans_membre_actif_ne_recoit_rien(): void
    {
        $equipe = $this->equipe([]);
        $mission = $this->mission();

        $this->assertFalse(app(MissionAssignmentService::class)->assignerEquipe($mission, $equipe));
        $this->assertNull($mission->fresh()->field_team_id);
    }

    public function test_un_membre_suspendu_de_la_societe_n_est_pas_assigne(): void
    {
        /*
         * Appartenir à une équipe et appartenir à la société sont deux choses : une équipe garde ses
         * lignes quand un salarié est suspendu. L'assigner confierait la mission à quelqu'un qui
         * n'a plus accès à l'application.
         */
        $actif = $this->membre(OrganizationRole::WORKER);
        $suspendu = $this->membre(OrganizationRole::WORKER);

        OrganizationMember::where('organization_account_id', $this->org->id)
            ->where('user_id', $suspendu->id)
            ->update(['status' => 'suspended']);

        $equipe = $this->equipe([$actif, $suspendu]);
        $mission = $this->mission();

        app(MissionAssignmentService::class)->assignerEquipe($mission, $equipe);

        $this->assertDatabaseMissing('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $suspendu->id,
            'assignment_status' => 'assigned',
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Basculer d'une équipe à l'autre
    // ──────────────────────────────────────────────────────

    public function test_basculer_vers_une_autre_equipe_libere_les_membres_non_repris(): void
    {
        /*
         * SANS CELA, LA MISSION ACCUMULE LES INTERVENANTS de toutes les équipes qui y sont passées,
         * et le répartiteur la croit sur-dotée. `assigner()` ne libère que les RESPONSABLES des
         * autres — délibérément, sinon remplacer le chef la veille désassignerait toute l'équipe :
         * les renforts sont donc à la charge d'`assignerEquipe()`.
         */
        $chefA = $this->membre(OrganizationRole::TEAM_LEAD);
        $renfortA = $this->membre(OrganizationRole::WORKER);
        $chefB = $this->membre(OrganizationRole::TEAM_LEAD);

        $equipeA = $this->equipe([$chefA, $renfortA], chef: $chefA);
        $equipeB = $this->equipe([$chefB], chef: $chefB);

        $decideur = $this->membre(OrganizationRole::OWNER);
        $mission = $this->mission();

        $service = app(MissionAssignmentService::class);
        $service->assignerEquipe($mission, $equipeA);
        $service->assignerEquipe($mission->fresh(), $equipeB, $decideur->id, 'Équipe A en congé');

        $mission->refresh();

        $this->assertSame($equipeB->id, $mission->field_team_id);
        $this->assertSame($chefB->id, $mission->lead_provider_user_id);

        $ancienChef = MissionAssignment::where('mission_id', $mission->id)
            ->where('user_id', $chefA->id)->first();
        $ancienRenfort = MissionAssignment::where('mission_id', $mission->id)
            ->where('user_id', $renfortA->id)->first();

        $this->assertSame('reassigned', $ancienChef?->assignment_status);
        $this->assertSame('released', $ancienRenfort?->assignment_status);

        // QUI a décidé, et pourquoi — la table savait dire `reassigned` et rien d'autre.
        $this->assertSame($decideur->id, $ancienChef?->reassigned_by);
        $this->assertSame('Équipe A en congé', $ancienChef?->reassignment_reason);
        $this->assertSame($decideur->id, $ancienRenfort?->reassigned_by);
    }

    public function test_un_membre_present_dans_les_deux_equipes_reste_assigne(): void
    {
        // Il n'a aucune raison d'être libéré puis réassigné : la mission ne le quitte pas.
        $partage = $this->membre(OrganizationRole::WORKER);
        $chefA = $this->membre(OrganizationRole::TEAM_LEAD);
        $chefB = $this->membre(OrganizationRole::TEAM_LEAD);

        $equipeA = $this->equipe([$chefA, $partage], chef: $chefA);
        $equipeB = $this->equipe([$chefB, $partage], chef: $chefB);

        $mission = $this->mission();
        $service = app(MissionAssignmentService::class);
        $service->assignerEquipe($mission, $equipeA);
        $service->assignerEquipe($mission->fresh(), $equipeB);

        $this->assertSame(
            'assigned',
            MissionAssignment::where('mission_id', $mission->id)
                ->where('user_id', $partage->id)->first()?->assignment_status,
        );
    }

    // ──────────────────────────────────────────────────────
    // Qui peut réassigner — l'exigence 5 et sa portée
    // ──────────────────────────────────────────────────────

    public function test_le_dispatcheur_reassigne_partout_dans_sa_societe(): void
    {
        $dispatcheur = $this->membre(OrganizationRole::DISPATCHER);

        $this->assertTrue(
            app(ReassignmentPolicy::class)->peutReassigner($dispatcheur, $this->mission()),
        );
    }

    public function test_le_chef_d_equipe_reassigne_dans_son_equipe_et_pas_ailleurs(): void
    {
        /*
         * LA MOITIÉ DE L'EXIGENCE 5 QUE LA MATRICE NE PEUT PAS EXPRIMER. Le lot 1 a accordé
         * `missions.assign` à `team_lead` en notant que la portée serait bornée ici : sans cette
         * borne, un chef d'équipe redistribuait les missions de toute la société.
         */
        $chef = $this->membre(OrganizationRole::TEAM_LEAD);
        $coequipier = $this->membre(OrganizationRole::WORKER);

        $sienne = $this->mission();
        app(MissionAssignmentService::class)
            ->assignerEquipe($sienne, $this->equipe([$chef, $coequipier], chef: $chef));

        $politique = app(ReassignmentPolicy::class);

        $this->assertTrue($politique->peutReassigner($chef, $sienne->fresh()));
        $this->assertFalse(
            $politique->peutReassigner($chef, $this->mission()),
            'Une mission sans équipe n’est l’équipe de personne.',
        );
    }

    public function test_le_chef_d_equipe_ne_touche_pas_a_l_equipe_voisine(): void
    {
        $chef = $this->membre(OrganizationRole::TEAM_LEAD);
        $voisin = $this->membre(OrganizationRole::WORKER);

        $this->equipe([$chef], chef: $chef);
        $missionVoisine = $this->mission();
        app(MissionAssignmentService::class)
            ->assignerEquipe($missionVoisine, $this->equipe([$voisin]));

        $this->assertFalse(
            app(ReassignmentPolicy::class)->peutReassigner($chef, $missionVoisine->fresh()),
        );
    }

    public function test_le_meneur_d_equipe_sans_rang_reassigne_les_siennes(): void
    {
        /*
         * LES DEUX NOTIONS DE CHEF D'ÉQUIPE, RÉCONCILIÉES. `field_teams.team_lead_user_id` désigne
         * quelqu'un qui répond de l'équipe au quotidien, sans forcément porter le RANG
         * `OrganizationRole::TEAM_LEAD` ni la clé `missions.assign`. Lui refuser d'échanger deux de
         * ses membres l'obligerait à appeler le bureau pour un geste qui lui revient.
         */
        $meneur = $this->membre(OrganizationRole::WORKER);
        $equipier = $this->membre(OrganizationRole::WORKER);

        $mission = $this->mission();
        app(MissionAssignmentService::class)
            ->assignerEquipe($mission, $this->equipe([$meneur, $equipier], chef: $meneur));

        $politique = app(ReassignmentPolicy::class);

        $this->assertTrue($politique->peutReassigner($meneur, $mission->fresh()));
        // Un simple équipier, lui, n'a rien demandé de tel.
        $this->assertFalse($politique->peutReassigner($equipier, $mission->fresh()));
    }

    public function test_le_rang_ne_traverse_pas_les_societes(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $ownerAilleurs = $this->membre(OrganizationRole::OWNER, $autreOrg);

        $this->assertFalse(
            app(ReassignmentPolicy::class)->peutReassigner($ownerAilleurs, $this->mission()),
        );
    }

    public function test_le_worker_ne_reassigne_rien(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $mission = $this->mission();

        app(MissionAssignmentService::class)
            ->assignerEquipe($mission, $this->equipe([$worker]));

        $this->assertFalse(
            app(ReassignmentPolicy::class)->peutReassigner($worker, $mission->fresh()),
            'Appartenir à l’équipe ne donne pas le droit d’en redistribuer le travail.',
        );
    }

    // ──────────────────────────────────────────────────────
    // L'API
    // ──────────────────────────────────────────────────────

    public function test_l_api_confie_une_mission_a_une_equipe(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $a = $this->membre(OrganizationRole::WORKER);
        $b = $this->membre(OrganizationRole::WORKER);

        $equipe = $this->equipe([$a, $b], chef: $a);
        $mission = $this->mission();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/assign-team", [
                'field_team_id' => $equipe->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.field_team_id', $equipe->id)
            ->assertJsonPath('data.lead_user_id', $a->id);
    }

    public function test_l_api_refuse_l_equipe_d_une_autre_societe(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);
        $equipeAdverse = $this->equipe([$this->membre(OrganizationRole::WORKER, $autreOrg)], org: $autreOrg);
        $mission = $this->mission();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/missions/{$mission->id}/assign-team", [
                'field_team_id' => $equipeAdverse->id,
            ])
            ->assertNotFound();
    }

    public function test_l_api_refuse_au_chef_d_equipe_une_mission_qui_n_est_pas_la_sienne(): void
    {
        $chef = $this->membre(OrganizationRole::TEAM_LEAD);
        $this->equipe([$chef], chef: $chef);

        $sienne = $this->equipe([$this->membre(OrganizationRole::WORKER)]);
        $missionVoisine = $this->mission();
        app(MissionAssignmentService::class)->assignerEquipe($missionVoisine, $sienne);

        $this->actingAs($chef, 'sanctum')
            ->postJson("/api/provider/company/missions/{$missionVoisine->id}/assign", [
                'user_id' => $chef->id,
            ])
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────
    // Le renfort retrouve SA mission
    // ──────────────────────────────────────────────────────

    public function test_le_renfort_d_une_equipe_voit_et_ouvre_sa_mission(): void
    {
        /*
         * LE CRITÈRE QUI RÉVÈLE UN TROU AILLEURS. `/provider/missions/active` n'admettait que le
         * responsable et les assignments `accepted` — l'état du parcours MARKETPLACE, où un
         * indépendant répond oui à une proposition. Un salarié n'accepte rien : son employeur
         * décide, et l'assignation naît `assigned`. Les renforts d'une équipe envoyée sur un
         * chantier ne trouvaient donc leur mission nulle part, et se voyaient refuser son ouverture.
         */
        $chef = $this->membre(OrganizationRole::TEAM_LEAD);
        $renfort = $this->membre(OrganizationRole::WORKER);

        $mission = $this->mission();
        app(MissionAssignmentService::class)
            ->assignerEquipe($mission, $this->equipe([$chef, $renfort], chef: $chef));

        $reponse = $this->actingAs($renfort, 'sanctum')
            ->getJson('/api/provider/missions/active')
            ->assertOk();

        $this->assertContains(
            $mission->id,
            array_column($reponse->json('data'), 'id'),
            'Le renfort doit trouver sa mission dans sa liste active.',
        );

        $this->actingAs($renfort, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertOk();
    }

    public function test_une_offre_marketplace_en_attente_ne_devient_pas_une_mission_active(): void
    {
        /*
         * L'AUTRE MOITIÉ DE LA RÈGLE, ET ELLE COMPTE AUTANT. `assigned` désigne aussi une OFFRE
         * envoyée à un indépendant et pas encore acceptée. Ouvrir la liste sur ce seul statut
         * laisserait démarrer une mission qu'on a seulement proposée — c'est l'appartenance de la
         * MISSION à une société qui distingue une décision d'une proposition.
         */
        $independant = User::factory()->employe()->create(['email_verified_at' => now()]);
        $independant->providerProfile()->create([
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
        ]);

        $missionSansSociete = Mission::factory()->create([
            'provider_organization_id' => null,
            'status' => 'assigned',
        ]);

        $missionSansSociete->assignments()->create([
            'user_id' => $independant->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $reponse = $this->actingAs($independant->fresh(), 'sanctum')
            ->getJson('/api/provider/missions/active')
            ->assertOk();

        $this->assertNotContains(
            $missionSansSociete->id,
            array_column($reponse->json('data'), 'id'),
            'Une offre en attente de réponse n’est pas une mission active.',
        );
    }

    // ──────────────────────────────────────────────────────
    // Composition d'équipe par la société
    // ──────────────────────────────────────────────────────

    public function test_la_societe_peuple_et_vide_ses_propres_equipes(): void
    {
        /*
         * `field_team_members` n'était manipulable que depuis l'administration de la PLATEFORME :
         * une société qui créait son équipe ne pouvait pas la peupler, et une équipe vide ne peut
         * recevoir aucune mission.
         */
        $owner = $this->membre(OrganizationRole::OWNER);
        $recrue = $this->membre(OrganizationRole::WORKER);
        $equipe = $this->equipe([]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/field-teams/{$equipe->id}/members", ['user_id' => $recrue->id])
            ->assertCreated();

        $this->assertDatabaseHas('field_team_members', [
            'field_team_id' => $equipe->id,
            'user_id' => $recrue->id,
            'is_active' => true,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/provider/company/field-teams/{$equipe->id}/members/{$recrue->id}")
            ->assertOk();

        // La ligne SURVIT : l'historique d'une équipe doit dire qui en a fait partie.
        $this->assertDatabaseHas('field_team_members', [
            'field_team_id' => $equipe->id,
            'user_id' => $recrue->id,
            'is_active' => false,
        ]);
    }

    public function test_une_equipe_n_enrole_pas_l_employe_d_une_autre_societe(): void
    {
        $autreOrg = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $owner = $this->membre(OrganizationRole::OWNER);
        $concurrent = $this->membre(OrganizationRole::WORKER, $autreOrg);
        $equipe = $this->equipe([]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/provider/company/field-teams/{$equipe->id}/members", ['user_id' => $concurrent->id])
            ->assertNotFound();

        $this->assertDatabaseMissing('field_team_members', [
            'field_team_id' => $equipe->id,
            'user_id' => $concurrent->id,
        ]);
    }

    public function test_le_worker_ne_compose_pas_les_equipes(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $equipe = $this->equipe([]);

        $this->actingAs($worker, 'sanctum')
            ->postJson("/api/provider/company/field-teams/{$equipe->id}/members", ['user_id' => $worker->id])
            ->assertForbidden();
    }

    public function test_retirer_le_meneur_lui_retire_aussi_la_barre(): void
    {
        /*
         * Laisser `team_lead_user_id` désigner un partant donnerait la mission au premier membre
         * actif à l'assignation suivante — sans que rien ne l'explique — et `ReassignmentPolicy`
         * continuerait de lui accorder la main sur les missions de l'équipe.
         */
        $owner = $this->membre(OrganizationRole::OWNER);
        $meneur = $this->membre(OrganizationRole::WORKER);
        $equipe = $this->equipe([$meneur], chef: $meneur);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/provider/company/field-teams/{$equipe->id}/members/{$meneur->id}")
            ->assertOk();

        $this->assertNull($equipe->fresh()->team_lead_user_id);
    }
    // ──────────────────────────────────────────────────────
    // L'écran web : les gestes existent ET s'affichent
    // ──────────────────────────────────────────────────────

    public function test_l_ecran_web_rend_les_boutons_de_composition(): void
    {
        /*
         * DÉCLARER N'EST PAS RENDRE JOIGNABLE. Une action Livewire sans bouton dans la vue est du
         * code mort, et ce dépôt en a déjà payé le prix — cinq écrans société livrés, testés,
         * atteignables par personne. On vérifie donc le RENDU, pas seulement l'existence des
         * méthodes.
         */
        $owner = $this->membre(OrganizationRole::OWNER);
        $recrue = $this->membre(OrganizationRole::WORKER);
        $equipe = $this->equipe([]);

        \Livewire\Livewire::actingAs($owner)
            ->test(\App\Livewire\ProviderCompany\FieldTeams::class)
            ->assertSee('Composition')
            ->call('ouvrirLaComposition', $equipe->id)
            ->assertSee('une équipe vide ne peut recevoir aucune mission', false)
            ->assertSee($recrue->name)
            ->call('ajouterMembre', $equipe->id, $recrue->id);

        $this->assertDatabaseHas('field_team_members', [
            'field_team_id' => $equipe->id,
            'user_id' => $recrue->id,
            'is_active' => true,
        ]);
    }

    public function test_l_ecran_web_refuse_la_composition_a_qui_n_a_que_la_lecture(): void
    {
        // `team.view` ouvre l'écran ; composer relève de `team.manage`. Le chef d'équipe consulte
        // légitimement ses agences sans redessiner les effectifs.
        $chef = $this->membre(OrganizationRole::TEAM_LEAD);
        $recrue = $this->membre(OrganizationRole::WORKER);
        $equipe = $this->equipe([]);

        \Livewire\Livewire::actingAs($chef)
            ->test(\App\Livewire\ProviderCompany\FieldTeams::class)
            ->call('ajouterMembre', $equipe->id, $recrue->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('field_team_members', [
            'field_team_id' => $equipe->id,
            'user_id' => $recrue->id,
        ]);
    }
}
