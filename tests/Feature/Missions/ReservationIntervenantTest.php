<?php

namespace Tests\Feature\Missions;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Missions\MissionAssignmentService;
use App\Services\Organizations\OrganizationMemberAdministration;
use App\Services\Tips\TipService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** QUI INTERVIENT — UNE SEULE RÉPONSE, PARTOUT. */
class ReservationIntervenantTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $contexte;

    private OrganizationAccount $societe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contexte = $this->createCoverageContext();

        $this->societe = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function salarie(): User
    {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $this->societe->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $this->societe->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    private function membre(User $user): OrganizationMember
    {
        return OrganizationMember::where('organization_account_id', $this->societe->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    /**
     * Une réservation confirmée, sa mission, et un premier intervenant nommé partout — l'état nominal, celui où les trois colonnes s'accordent.
     *
     * @return array{0: Booking, 1: Mission, 2: User}
     */
    private function missionAssigneeA(User $intervenant, string $statut = 'confirme'): array
    {
        $client = User::factory()->client()->create();

        $reservation = Booking::factory()
            ->forStructuredContext(
                $this->contexte['service'],
                $this->contexte['zone'],
                $this->contexte['postalCode'],
            )
            // TOUJOURS CRÉÉE `confirme`, quel que soit l'état demandé.
            ->create([
                'client_id' => $client->id,
                'customer_user_id' => $client->id,
                'status' => 'confirme',
                'devis_estime' => 120,
                'employe_id' => $intervenant->id,
            ]);

        // ON REPREND LA MISSION QUE LA RÉSERVATION A DÉJÀ.
        $mission = $reservation->missions()->latest('id')->firstOrFail();
        $mission->forceFill([
            'provider_organization_id' => $this->societe->id,
            'lead_employee_id' => $intervenant->id,
            'lead_provider_user_id' => $intervenant->id,
            'status' => MissionStatus::ASSIGNED,
            'planned_start_at' => now()->addHour(),
        ])->save();

        // L'affectation du responsable existe déjà elle aussi : `syncFromRendezVous` appelle
        // `syncLeadAssignment` dès que la réservation nomme quelqu'un. En créer une seconde
        // heurtait la contrainte d'unicité (mission, utilisateur).
        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $intervenant->id,
        ]);

        if ($statut !== 'confirme') {
            $reservation->forceFill(['status' => $statut])->save();
        }

        return [$reservation->fresh(), $mission->fresh(), $client];
    }

    /** LE CŒUR DE LA FUSION : après réassignation, une seule personne répond, et c'est la bonne. */
    public function test_apres_reassignation_la_reservation_nomme_le_nouvel_intervenant(): void
    {
        $ancien = $this->salarie();
        $nouveau = $this->salarie();
        [$reservation, $mission] = $this->missionAssigneeA($ancien);

        app(MissionAssignmentService::class)->assigner($mission, $this->membre($nouveau));

        $this->assertSame(
            $nouveau->id,
            $reservation->fresh()->intervenantId(),
            'La réservation désigne encore la personne remplacée.',
        );

        $mission = $mission->fresh();

        // Les trois colonnes, remises d'accord — c'est la cause, pas le symptôme.
        $this->assertSame($nouveau->id, (int) $mission->lead_provider_user_id);
        $this->assertSame($nouveau->id, (int) $mission->lead_employee_id);
        $this->assertSame($nouveau->id, (int) $reservation->fresh()->employe_id);
    }

    /** LE DROIT DE REGARD SUIT LE TRAVAIL, dans les deux sens : le nouveau l'obtient, l'ancien le perd. */
    public function test_le_droit_de_regard_sur_le_rendez_vous_suit_la_reassignation(): void
    {
        $ancien = $this->salarie();
        $nouveau = $this->salarie();
        [$reservation, $mission] = $this->missionAssigneeA($ancien);

        app(MissionAssignmentService::class)->assigner($mission, $this->membre($nouveau));
        $reservation = $reservation->fresh();

        $this->assertTrue($nouveau->can('view', $reservation), 'Celui qui intervient ne peut pas voir le rendez-vous.');
        $this->assertFalse($ancien->can('view', $reservation), 'La personne remplacée garde l’accès au client.');
    }

    /** UNE AFFECTATION RÉVOQUÉE N'OUVRE PLUS LA MISSION. */
    public function test_une_affectation_revoquee_ne_donne_plus_acces_a_la_mission(): void
    {
        $ancien = $this->salarie();
        $nouveau = $this->salarie();
        [, $mission] = $this->missionAssigneeA($ancien);

        app(MissionAssignmentService::class)->assigner($mission, $this->membre($nouveau));
        $mission = $mission->fresh();

        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'user_id' => $ancien->id,
            'assignment_status' => 'reassigned',
        ]);

        $this->assertTrue($mission->estIntervenant($nouveau));
        $this->assertFalse($mission->estIntervenant($ancien), 'Une ligne révoquée ouvre encore la mission.');

        $this->assertTrue($nouveau->can('view', $mission));
        $this->assertFalse($ancien->can('view', $mission));
        $this->assertFalse($ancien->can('start', $mission), 'La personne remplacée peut encore démarrer la mission.');
    }

    /** LE POURBOIRE VA À CELUI QUI EST VENU. */
    public function test_le_pourboire_revient_a_celui_qui_a_fait_le_travail(): void
    {
        $ancien = $this->salarie();
        $nouveau = $this->salarie();
        [$reservation, $mission, $client] = $this->missionAssigneeA($ancien, statut: 'termine');

        app(MissionAssignmentService::class)->assigner($mission, $this->membre($nouveau));

        $pourboire = app(TipService::class)->create($client, $reservation->fresh(), 1500);

        $this->assertSame(
            $nouveau->id,
            (int) $pourboire->provider_user_id,
            'Le client remercie quelqu’un qui n’est jamais venu.',
        );
    }

    /** QUAND LA MISSION NE DÉSIGNE PERSONNE, LA RÉSERVATION RESTE LA SOURCE. */
    public function test_sans_responsable_sur_la_mission_la_reservation_fait_foi(): void
    {
        $intervenant = $this->salarie();
        [$reservation] = $this->missionAssigneeA($intervenant);

        $reservation->missions()->update(['lead_provider_user_id' => null, 'lead_employee_id' => null]);

        $this->assertSame($intervenant->id, $reservation->fresh()->intervenantId());
    }

    /** Une réservation sans personne ne doit inventer personne. */
    public function test_une_reservation_sans_intervenant_ne_repond_personne(): void
    {
        $reservation = Booking::factory()
            ->forStructuredContext(
                $this->contexte['service'],
                $this->contexte['zone'],
                $this->contexte['postalCode'],
            )
            ->create(['employe_id' => null, 'assigned_provider_user_id' => null]);

        $this->assertNull($reservation->intervenantId());
        $this->assertNull($reservation->intervenant());
    }

    /** LE CHEMIN INVERSE : la mission suit la réservation. */
    public function test_assigner_depuis_la_reservation_entraine_la_mission(): void
    {
        $ancien = $this->salarie();
        $nouveau = $this->salarie();
        [$reservation, $mission] = $this->missionAssigneeA($ancien);

        $reservation->employe_id = $nouveau->id;
        $reservation->save();

        $mission = $mission->fresh();

        $this->assertSame($nouveau->id, (int) $mission->lead_provider_user_id);
        $this->assertSame($nouveau->id, (int) $mission->lead_employee_id);
        $this->assertSame($nouveau->id, $reservation->fresh()->intervenantId());
    }

    /** UNE MISSION EN COURS NE RECULE PAS. */
    public function test_le_statut_d_une_mission_commencee_ne_recule_pas(): void
    {
        $ancien = $this->salarie();
        $nouveau = $this->salarie();
        [$reservation, $mission] = $this->missionAssigneeA($ancien, statut: 'sur_place');

        $mission->forceFill(['status' => MissionStatus::STARTED])->save();

        $reservation->refresh();
        $reservation->employe_id = $nouveau->id;
        $reservation->save();

        $mission = $mission->fresh();

        $this->assertSame(MissionStatus::STARTED, $mission->status);
        $this->assertSame($nouveau->id, (int) $mission->lead_provider_user_id);
    }

    /** LE DÉPART D'UN SALARIÉ REND L'INTERVENTION À RÉATTRIBUER — et il faut la VOIR. */
    public function test_le_depart_d_un_salarie_rend_la_reservation_visible_comme_non_attribuee(): void
    {
        $partant = $this->salarie();
        [$reservation] = $this->missionAssigneeA($partant);

        app(OrganizationMemberAdministration::class)->libererLAvenir($partant->id, $this->societe->id);

        $this->assertNull($reservation->fresh()->intervenantId());

        $this->assertTrue(
            Booking::query()->sansIntervenant()->whereKey($reservation->id)->exists(),
            'La réservation libérée reste invisible des listes « sans employé ».',
        );
    }

    /** UNE RETENUE ACTIVE ARRÊTE LE NETTOYAGE DU DÉPART — et c'est voulu. */
    public function test_une_retenue_active_laisse_la_reservation_au_nom_du_partant(): void
    {
        $partant = $this->salarie();
        $second = $this->salarie();
        [$reservation] = $this->missionAssigneeA($partant);

        $reservation->forceFill([
            'payment_status' => 'authorized',
            'stripe_payment_intent_id' => 'pi_retenue_active',
        ])->save();

        app(OrganizationMemberAdministration::class)->libererLAvenir($partant->id, $this->societe->id);

        $this->assertSame(
            $partant->id,
            (int) $reservation->fresh()->employe_id,
            'La réservation a été déliée alors qu’une retenue désigne encore le compte du partant.',
        );

        // Et la garde tient toujours : lui substituer quelqu'un reste refusé.
        $this->expectException(ValidationException::class);

        $reservation->refresh();
        $reservation->employe_id = $second->id;
        $reservation->save();
    }

    /** LES DEUX FORMULATIONS DE LA MÊME RÈGLE DOIVENT RENDRE LE MÊME VERDICT. */
    public function test_le_filtre_sql_et_le_resolveur_disent_la_meme_chose(): void
    {
        $a = $this->salarie();
        $b = $this->salarie();

        // Les deux d'accord.
        [$accord] = $this->missionAssigneeA($a);

        // La mission seule désigne quelqu'un — le cas d'une réassignation de société.
        [$missionSeule, $m2] = $this->missionAssigneeA($a);
        Booking::query()->whereKey($missionSeule->id)
            ->update(['employe_id' => null, 'assigned_provider_user_id' => null]);
        Mission::query()->whereKey($m2->id)->update(['lead_provider_user_id' => $b->id]);

        // Divergence héritée : écrite sans passer par les modèles, comme une vieille ligne.
        [$divergente, $m3] = $this->missionAssigneeA($a);
        Mission::query()->whereKey($m3->id)->update(['lead_provider_user_id' => $b->id]);

        // La réservation seule : mission sans responsable, c'est le parcours web.
        [$reservationSeule, $m4] = $this->missionAssigneeA($a);
        Mission::query()->whereKey($m4->id)->update(['lead_provider_user_id' => null]);

        // Le repli le plus lointain : ni mission, ni `employe_id`, seulement `assigned_employee_id`.
        // Cette colonne manquait au filtre SQL pendant que le résolveur la lisait.
        [$repliLointain, $m6] = $this->missionAssigneeA($a);
        Booking::query()->whereKey($repliLointain->id)->update([
            'employe_id' => null,
            'assigned_provider_user_id' => null,
            'assigned_employee_id' => $b->id,
        ]);
        Mission::query()->whereKey($m6->id)->update(['lead_provider_user_id' => null, 'lead_employee_id' => null]);

        // Personne.
        [$personne, $m5] = $this->missionAssigneeA($a);
        Booking::query()->whereKey($personne->id)->update(['employe_id' => null, 'assigned_provider_user_id' => null]);
        Mission::query()->whereKey($m5->id)->update(['lead_provider_user_id' => null, 'lead_employee_id' => null]);

        $toutes = Booking::query()->with('missions')->get();

        // Les deux utilisateurs releves ensemble : quand le filtre SQL diverge du resolveur, il
        // diverge pour TOUS, et voir les deux ecarts dit si c'est la meme cause.
        $divergences = [];

        foreach ([$a, $b] as $utilisateur) {
            $parLeResolveur = $toutes
                ->filter(fn (Booking $r) => $r->intervenantId() === $utilisateur->id)
                ->pluck('id')->sort()->values()->all();

            $parLeFiltre = Booking::query()->intervenantEst($utilisateur->id)
                ->pluck('id')->sort()->values()->all();

            if ($parLeResolveur !== $parLeFiltre) {
                $divergences[] = sprintf(
                    'utilisateur #%d : resolveur [%s], filtre SQL [%s]',
                    $utilisateur->id,
                    implode(',', $parLeResolveur),
                    implode(',', $parLeFiltre),
                );
            }
        }

        $this->assertSame([], $divergences, 'Le filtre SQL et le resolveur ne designent pas les memes reservations.');

        $sansParLeResolveur = $toutes
            ->filter(fn (Booking $r) => $r->intervenantId() === null)
            ->pluck('id')->sort()->values()->all();

        $this->assertSame(
            $sansParLeResolveur,
            Booking::query()->sansIntervenant()->pluck('id')->sort()->values()->all(),
        );

        // Et les cas construits sont bien ceux qu'on croit — sans quoi le test se compare à lui-même.
        $this->assertSame($a->id, $accord->fresh()->intervenantId());
        $this->assertSame($b->id, $missionSeule->fresh()->intervenantId());
        $this->assertSame($b->id, $divergente->fresh()->intervenantId());
        $this->assertSame($a->id, $reservationSeule->fresh()->intervenantId());
        $this->assertSame($b->id, $repliLointain->fresh()->intervenantId());
        $this->assertNull($personne->fresh()->intervenantId());
    }

    /** LA GARDE — pour que l'écart ne se rouvre pas ligne par ligne. */
    public function test_aucun_lecteur_ne_resout_l_intervenant_depuis_la_seule_reservation(): void
    {
        $autorises = [
            'app/Models/Booking.php',
            'app/Services/Insurance/InsurancePricingEngine.php',
        ];

        $fautifs = [];

        foreach ($this->fichiersPhpDeLApplication() as $chemin) {
            $relatif = str_replace('\\', '/', substr($chemin, strlen(base_path()) + 1));

            if (in_array($relatif, $autorises, true)) {
                continue;
            }

            $contenu = (string) file_get_contents($chemin);

            if (preg_match('/employe_id\s*\?\?|lead_employee_id\s*===/', $contenu)) {
                $fautifs[] = $relatif;
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            'Ces fichiers déduisent l’intervenant d’une seule colonne au lieu de passer par '.
            "Booking::intervenantId() ou Mission::estIntervenant() :\n".implode("\n", $fautifs),
        );
    }

    /** ET AUCUN FILTRE NE CHERCHE UN INTERVENANT SUR LA SEULE COLONNE. */
    public function test_aucun_filtre_ne_cherche_un_intervenant_sur_la_seule_colonne(): void
    {
        $autorises = [
            // `feedback.employe_id` est une AUTRE COLONNE, sur une autre table : elle nomme le destinataire d'un avis déjà déposé, pas qui doit se rendre quelque part.
            'app/Models/Feedback.php',
            'app/Services/Badges/ProviderBadgeEngine.php',
            'app/Services/Matching/ProviderPerformanceCalculator.php',
            'app/Services/Quality/WorkerQualityScoreService.php',
            'app/Services/Rating/RatingAggregationService.php',

            // La règle elle-même.
            'app/Models/Booking.php',

            // Vise la personne QUI PART, pas celle qui intervient : le filtre doit rester sur la
            // colonne, c'est elle qu'on vient nettoyer.
            'app/Services/Organizations/OrganizationMemberAdministration.php',

            // Export RGPD : ÉLARGIT au lieu de basculer — une réservation où la personne a été
            // nommée puis remplacée la concerne toujours.
            'app/Services/Gdpr/DataExportService.php',
        ];

        $fautifs = [];

        foreach ($this->fichiersPhpDeLApplication() as $chemin) {
            $relatif = str_replace('\\', '/', substr($chemin, strlen(base_path()) + 1));

            if (in_array($relatif, $autorises, true)) {
                continue;
            }

            if (preg_match("/->where\('employe_id'/", (string) file_get_contents($chemin))) {
                $fautifs[] = $relatif;
            }
        }

        $this->assertSame(
            [],
            $fautifs,
            "Ces fichiers filtrent sur `employe_id` au lieu du scope `intervenantEst()` :\n".implode("\n", $fautifs),
        );
    }

    /** @return list<string> */
    private function fichiersPhpDeLApplication(): array
    {
        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        $fichiers = [];

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && $fichier->getExtension() === 'php') {
                $fichiers[] = $fichier->getPathname();
            }
        }

        return $fichiers;
    }
}
