<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Jobs\Missions\AutoAssignerMissionsJob;
use App\Models\InternalAssignmentDecision;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\ProviderSiteAssignment;
use App\Models\User;
use App\Services\Missions\InternalAutoAssignmentEngine;
use App\Services\Missions\InternalDispatchRunner;
use App\Services\Missions\WorkerAvailabilityService;
use App\Services\Organizations\OrganizationNotifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * LOT 4 — RÉPARTIR SANS QUE PERSONNE NE REGARDE.
 *
 * Un moteur qui distribue le travail d'une société doit répondre à trois questions avant qu'on lui
 * fasse confiance : sur quoi il se fonde, ce qu'il fait quand il ne trouve personne, et comment il
 * se comporte quand on appuie deux fois.
 *
 * LA DISPONIBILITÉ EST UN FILTRE, PAS UN SCORE. Quelqu'un déjà pris sort du classement au lieu d'y
 * descendre — le pondérer laisserait un très bon score compenser une impossibilité physique, et
 * enverrait la même personne à deux endroits.
 */
class AutoAssignationTest extends TestCase
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

    private function membre(OrganizationRole $role = OrganizationRole::WORKER): User
    {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $this->org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $this->org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $this->org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    private function mission(?string $debut = null, ?int $siteId = null): Mission
    {
        return Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'organization_site_id' => $siteId,
            'lead_provider_user_id' => null,
            'planned_start_at' => $debut ?? now()->addDay()->setTime(9, 0),
            'planned_end_at' => ($debut ? now()->parse($debut) : now()->addDay()->setTime(9, 0))->copy()->addHours(2),
        ]);
    }

    // ──────────────────────────────────────────────────────
    // La disponibilité — extraite, et éliminatoire
    // ──────────────────────────────────────────────────────

    public function test_la_disponibilite_se_calcule_en_une_requete_sans_creneaux_publies(): void
    {
        /*
         * INTERDIT DE PASSER PAR `AvailabilityService` : les créneaux publiés sont un concept
         * d'INDÉPENDANT. Il rend `false` pour un salarié qui n'en déclare aucun — c'est-à-dire tous
         * — et coûte ~200 ms par personne. Ce test vaut donc autant par ce qu'il affirme (libre)
         * que par ce qu'il interdit.
         */
        $a = $this->membre();
        $b = $this->membre();

        $verdicts = app(WorkerAvailabilityService::class)->libresPour(
            organisationId: $this->org->id,
            debut: now()->addDay()->setTime(9, 0),
        );

        $this->assertTrue($verdicts[$a->id]);
        $this->assertTrue($verdicts[$b->id]);
    }

    public function test_quelqu_un_deja_pris_sur_le_creneau_n_est_pas_libre(): void
    {
        $occupe = $this->membre();
        $libre = $this->membre();

        $autre = $this->mission();
        $autre->assignments()->create([
            'user_id' => $occupe->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $verdicts = app(WorkerAvailabilityService::class)->libresPour(
            organisationId: $this->org->id,
            debut: now()->addDay()->setTime(9, 30),
        );

        $this->assertFalse($verdicts[$occupe->id]);
        $this->assertTrue($verdicts[$libre->id]);
    }

    public function test_une_mission_sans_fin_declaree_occupe_quand_meme(): void
    {
        // `orWhereNull(planned_end_at)` n'est pas de la prudence : sans elle, une mission sans fin
        // rendrait libre quelqu'un qui ne l'est pas.
        $occupe = $this->membre();

        $autre = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'planned_start_at' => now()->addDay()->setTime(9, 0),
            'planned_end_at' => null,
        ]);
        $autre->assignments()->create([
            'user_id' => $occupe->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $verdicts = app(WorkerAvailabilityService::class)->libresPour(
            organisationId: $this->org->id,
            debut: now()->addDay()->setTime(9, 30),
        );

        $this->assertFalse($verdicts[$occupe->id]);
    }

    // ──────────────────────────────────────────────────────
    // Le moteur — ce sur quoi il se fonde
    // ──────────────────────────────────────────────────────

    public function test_le_referent_du_site_passe_devant(): void
    {
        /*
         * C'EST LE CŒUR DU SUJET. Une société qui dessert vingt immeubles y place des habitués :
         * celui qui connaît le code de la porte et l'étage à ne pas déranger avant 10 h. C'est la
         * connaissance la plus chère à reconstituer, et la seule que le client remarque.
         */
        $referent = $this->membre();
        $quelconque = $this->membre();

        $site = OrganizationSite::factory()->create();

        ProviderSiteAssignment::create([
            'provider_organization_id' => $this->org->id,
            'organization_site_id' => $site->id,
            'user_id' => $referent->id,
            'role' => ProviderSiteAssignment::ROLE_LEAD,
        ]);

        $choix = app(InternalAutoAssignmentEngine::class)->choisirPour($this->mission(siteId: $site->id));

        $this->assertSame($referent->id, $choix['chosen_user_id']);
        $this->assertContains($quelconque->id, array_column($choix['candidates'], 'user_id'));
    }

    public function test_a_egalite_le_moins_charge_gagne(): void
    {
        $charge = $this->membre();
        $repose = $this->membre();

        // Une mission le MÊME JOUR mais sur un autre créneau : elle pèse sur la charge sans rendre
        // indisponible. C'est exactement ce que le critère mesure.
        $ailleurs = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'planned_start_at' => now()->addDay()->setTime(15, 0),
            'planned_end_at' => now()->addDay()->setTime(17, 0),
        ]);
        $ailleurs->assignments()->create([
            'user_id' => $charge->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $choix = app(InternalAutoAssignmentEngine::class)->choisirPour($this->mission());

        $this->assertSame($repose->id, $choix['chosen_user_id']);
    }

    public function test_la_rotation_departage_celui_qu_on_oublie(): void
    {
        /*
         * Sans rotation, le moteur choisirait toujours le même : le mieux placé le reste, son score
         * ne bouge pas, et l'équipe se partage entre surchargés et oubliés.
         *
         * Jamais assigné = le PLAFOND de rotation. Un nouvel arrivant doit passer devant celui qui
         * sort d'une mission hier ; lui donner 0 le laisserait au fond du classement précisément
         * parce qu'on ne lui a rien donné.
         */
        $recent = $this->membre();
        $jamaisVu = $this->membre();

        $hier = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'planned_start_at' => now()->subDay(),
            'planned_end_at' => now()->subDay()->addHours(2),
        ]);
        $hier->assignments()->create([
            'user_id' => $recent->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $choix = app(InternalAutoAssignmentEngine::class)->choisirPour($this->mission());

        $this->assertSame($jamaisVu->id, $choix['chosen_user_id']);
    }

    public function test_le_moteur_ne_choisit_personne_quand_tout_le_monde_est_pris(): void
    {
        $seul = $this->membre();

        $autre = $this->mission();
        $autre->assignments()->create([
            'user_id' => $seul->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $choix = app(InternalAutoAssignmentEngine::class)->choisirPour($this->mission());

        $this->assertNull($choix['chosen_user_id']);
    }

    public function test_sans_horaire_le_moteur_s_abstient(): void
    {
        /*
         * Toute la notion de disponibilité repose sur un créneau. Choisir au hasard serait pire que
         * ne rien faire : la mission paraîtrait couverte, et le conflit n'apparaîtrait que le jour
         * même.
         */
        $this->membre();

        $sansHoraire = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'planned_start_at' => null,
        ]);

        $this->assertNull(app(InternalAutoAssignmentEngine::class)->choisirPour($sansHoraire)['chosen_user_id']);
    }

    public function test_le_departage_est_rejouable_a_score_egal(): void
    {
        // Une décision automatique doit être REJOUABLE : sans second critère, deux candidats à
        // égalité seraient départagés par l'ordre de la base, qui varie.
        $premier = $this->membre();
        $this->membre();

        $moteur = app(InternalAutoAssignmentEngine::class);

        $this->assertSame($premier->id, $moteur->choisirPour($this->mission())['chosen_user_id']);
        $this->assertSame($premier->id, $moteur->choisirPour($this->mission())['chosen_user_id']);
    }

    // ──────────────────────────────────────────────────────
    // L'exécution — verrou, trace, alerte
    // ──────────────────────────────────────────────────────

    public function test_traiter_assigne_et_trace_le_detail_du_calcul(): void
    {
        $choisi = $this->membre();
        $mission = $this->mission();

        $decision = app(InternalDispatchRunner::class)
            ->traiter($mission, InternalAssignmentDecision::MODE_AUTO_BUTTON);

        $this->assertSame(InternalAssignmentDecision::STATUS_ASSIGNED, $decision->status);
        $this->assertSame($choisi->id, $decision->chosen_user_id);
        $this->assertSame($choisi->id, $mission->fresh()->lead_provider_user_id);

        // Le détail, pas seulement le gagnant : c'est la différence entre « le moteur a choisi
        // Karim » et « voici les candidats, leurs scores, et le pourquoi de chaque point ».
        $this->assertNotEmpty($decision->candidates);
        $this->assertArrayHasKey('detail', $decision->candidates[0]);
    }

    public function test_une_mission_deja_pourvue_est_laissee_tranquille(): void
    {
        /*
         * La revérification après verrou. Un humain a pu prendre la mission entre le moment où on
         * l'a listée et celui où on la traite : la réassigner par-dessus son choix serait pire que
         * ne rien faire.
         */
        $humain = $this->membre();
        $mission = $this->mission();
        $mission->update(['lead_provider_user_id' => $humain->id]);

        $decision = app(InternalDispatchRunner::class)
            ->traiter($mission, InternalAssignmentDecision::MODE_AUTO_BUTTON);

        $this->assertSame(InternalAssignmentDecision::STATUS_SKIPPED_LOCKED, $decision->status);
        $this->assertSame($humain->id, $mission->fresh()->lead_provider_user_id);
    }

    public function test_sans_candidat_la_decision_est_tracee(): void
    {
        $seul = $this->membre();

        $autre = $this->mission();
        $autre->assignments()->create([
            'user_id' => $seul->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
        ]);

        $decision = app(InternalDispatchRunner::class)
            ->traiter($this->mission(), InternalAssignmentDecision::MODE_AUTO_MODE);

        $this->assertSame(InternalAssignmentDecision::STATUS_NO_CANDIDATE, $decision->status);
        $this->assertNull($decision->chosen_user_id);
    }

    // ──────────────────────────────────────────────────────
    // Le bouton et le mode continu
    // ──────────────────────────────────────────────────────

    public function test_le_bouton_met_le_travail_en_file_plutot_que_de_le_faire(): void
    {
        Queue::fake();

        $owner = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/provider/company/missions/auto-assign')
            ->assertStatus(202);

        Queue::assertPushed(AutoAssignerMissionsJob::class);
    }

    public function test_le_worker_ne_declenche_pas_l_auto_assignation(): void
    {
        Queue::fake();

        $this->actingAs($this->membre(), 'sanctum')
            ->postJson('/api/provider/company/missions/auto-assign')
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_le_job_traite_l_arriere_et_ne_touche_pas_au_passe(): void
    {
        $this->membre();

        $aVenir = $this->mission();

        $passee = Mission::factory()->create([
            'provider_organization_id' => $this->org->id,
            'lead_provider_user_id' => null,
            'planned_start_at' => now()->subWeek(),
        ]);

        (new AutoAssignerMissionsJob($this->org->id))->handle(
            app(InternalDispatchRunner::class),
            app(OrganizationNotifier::class),
        );

        $this->assertNotNull($aVenir->fresh()->lead_provider_user_id);
        // Assigner rétroactivement une mission d'hier ne sert personne et fausserait la charge du
        // jour de celui qu'on désignerait.
        $this->assertNull($passee->fresh()->lead_provider_user_id);
    }

    public function test_le_job_est_unique_par_societe(): void
    {
        // Le double-clic est le geste le plus naturel quand rien ne semble se passer : deux passages
        // concurrents choisiraient les mêmes personnes pour les mêmes missions.
        $job = new AutoAssignerMissionsJob($this->org->id);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame((string) $this->org->id, $job->uniqueId());
    }

    public function test_le_mode_continu_est_desactive_par_defaut(): void
    {
        // Aucune société ne doit se mettre à distribuer son travail toute seule du fait d'un
        // déploiement.
        $this->assertFalse((bool) $this->org->fresh()->auto_assign_enabled);
    }

    public function test_le_mode_continu_s_active_et_se_lit(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/provider/company/auto-assign/settings', ['auto_assign_enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.auto_assign_enabled', true);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/provider/company/auto-assign/settings')
            ->assertOk()
            ->assertJsonPath('data.auto_assign_enabled', true);
    }

    // ──────────────────────────────────────────────────────
    // Disponibilité par l'API
    // ──────────────────────────────────────────────────────

    public function test_l_api_de_disponibilite_dit_qui_est_libre(): void
    {
        $libre = $this->membre();
        $owner = $this->membre(OrganizationRole::OWNER);

        $mission = $this->mission();

        $donnees = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/provider/company/availability?mission_id='.$mission->id)
            ->assertOk()
            ->json('data.workers');

        $parId = collect($donnees)->keyBy('user_id');

        $this->assertTrue($parId[$libre->id]['is_free']);
    }

    public function test_le_worker_n_interroge_pas_la_disponibilite_de_l_equipe(): void
    {
        $worker = $this->membre();
        $mission = $this->mission();

        $this->actingAs($worker, 'sanctum')
            ->getJson('/api/provider/company/availability?mission_id='.$mission->id)
            ->assertForbidden();
    }

    // ──────────────────────────────────────────────────────
    // Les appareils du prestataire
    // ──────────────────────────────────────────────────────

    public function test_le_prestataire_enregistre_son_appareil_sur_une_route_honnete(): void
    {
        /*
         * L'enregistrement des jetons ne vivait que sous `/api/client/devices/*`. Une application
         * PRESTATAIRE devait donc appeler une route « client » pour recevoir ses notifications — ou
         * ne pas les recevoir, ce qui rendrait muettes toutes les alertes d'assignation.
         */
        $this->actingAs($this->membre(), 'sanctum')
            ->postJson('/api/provider/devices/register', [
                'token' => 'jeton-de-test-'.uniqid(),
                'platform' => 'android',
                'provider' => 'mock',
            ])
            ->assertSuccessful();
    }
}
