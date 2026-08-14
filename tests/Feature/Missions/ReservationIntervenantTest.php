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

/**
 * QUI INTERVIENT — UNE SEULE RÉPONSE, PARTOUT.
 *
 * TROIS COLONNES NOMMAIENT LA MÊME PERSONNE : `bookings.employe_id` (le prestataire de la commande),
 * `missions.lead_employee_id` (l'historique, écrit à la création) et `missions.lead_provider_user_id`
 * (ce qu'écrivent le dispatch et la réassignation). Sur un parcours nominal elles disent toutes la
 * même chose — c'est exactement ce qui a rendu l'écart invisible.
 *
 * ELLES NE DIVERGENT QU'À LA RÉASSIGNATION. `MissionAssignmentService` n'écrivait que la troisième :
 * l'ancien intervenant gardait l'accès au client, touchait le pourboire et recevait les étoiles,
 * pendant que celui qui avait fait le travail n'avait rien — et aucune erreur n'était levée nulle
 * part, des deux côtés la moitié lue semblait juste.
 *
 * Ce test tient la fusion : la réassignation remet les trois d'accord, et les lecteurs passent par
 * `Booking::intervenantId()` / `Mission::estIntervenant()` plutôt que par une colonne.
 */
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
     * Une réservation confirmée, sa mission, et un premier intervenant nommé partout — l'état
     * nominal, celui où les trois colonnes s'accordent.
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
            /*
             * TOUJOURS CRÉÉE `confirme`, quel que soit l'état demandé.
             *
             * `RendezVousObserver` ne fabrique la mission que pour une réservation confirmée (ou en
             * cours). Naître directement `termine` donnait une réservation SANS mission — un état
             * qu'aucun parcours réel ne produit, puisqu'on ne termine que ce qu'on a confirmé.
             */
            ->create([
                'client_id' => $client->id,
                'customer_user_id' => $client->id,
                'status' => 'confirme',
                'devis_estime' => 120,
                'employe_id' => $intervenant->id,
            ]);

        /*
         * ON REPREND LA MISSION QUE LA RÉSERVATION A DÉJÀ.
         *
         * `RendezVousObserver` en crée une à chaque enregistrement, via `updateOrCreate` sur
         * `booking_id` : en fabriquer une seconde construirait un état que la production ne peut
         * pas atteindre, et un test qui part d'un état impossible ne mesure plus le produit.
         */
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

    /**
     * LE CŒUR DE LA FUSION : après réassignation, une seule personne répond, et c'est la bonne.
     */
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

    /**
     * LE DROIT DE REGARD SUIT LE TRAVAIL, dans les deux sens : le nouveau l'obtient, l'ancien le perd.
     */
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

    /**
     * UNE AFFECTATION RÉVOQUÉE N'OUVRE PLUS LA MISSION.
     *
     * Les lignes d'affectation ne sont pas supprimées mais marquées `reassigned` : tout code qui
     * demandait « existe-t-il une affectation ? » répondait oui pour la personne qu'on venait
     * justement d'écarter — y compris `MissionPolicy`, donc le tableau d'exécution et la clôture.
     */
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

    /**
     * LE POURBOIRE VA À CELUI QUI EST VENU.
     *
     * Le bénéficiaire est résolu AVANT le portefeuille, dans `TipService` : corriger la ligne
     * comptable en aval ne rattrapait pas un `BookingTip` déjà émis au mauvais nom.
     */
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

    /**
     * QUAND LA MISSION NE DÉSIGNE PERSONNE, LA RÉSERVATION RESTE LA SOURCE.
     *
     * C'est la moitié conservatrice de la fusion, et elle n'est pas facultative :
     * `MissionFromRendezVousSyncService` crée les missions du parcours web avec
     * `lead_provider_user_id` à null. Faire de la mission une autorité absolue rendrait « personne »
     * pour toutes ces réservations — et fermerait l'accès, le pourboire et les étoiles à des
     * intervenants parfaitement assignés.
     */
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

    /**
     * LE CHEMIN INVERSE : la mission suit la réservation.
     *
     * Le planning d'administration écrit `bookings.employe_id` et sauvegarde, sans appeler la
     * synchronisation de mission — contrairement aux deux autres écrans d'assignation admin. La
     * mission restait donc au nom de la personne précédente, et c'est elle que lisent le mobile, le
     * dispatch, le portefeuille et la présence.
     */
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

    /**
     * UNE MISSION EN COURS NE RECULE PAS. Le statut ne suit que depuis et vers l'attente : réécrire
     * `assigned` sur une mission commencée la ferait disparaître des écrans terrain.
     *
     * L'état de départ est celui qu'un vrai parcours produit — réservation `sur_place`, mission
     * `started` — et non une mission commencée sous une réservation encore `confirme` : c'est
     * `MissionLifecycleService` qui fait avancer la réservation avec sa mission, les deux ne se
     * désynchronisent pas.
     */
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

    /**
     * LE DÉPART D'UN SALARIÉ REND L'INTERVENTION À RÉATTRIBUER — et il faut la VOIR.
     *
     * Libérer la mission ne suffisait pas : la réservation continuait de nommer la personne
     * partie, et c'est cette colonne que lisent les listes « sans employé ». L'intervention à venir
     * n'apparaissait donc nulle part comme à réattribuer.
     */
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

    /**
     * UNE RETENUE ACTIVE ARRÊTE LE NETTOYAGE DU DÉPART — et c'est voulu.
     *
     * Délier la réservation ici produirait une ligne « autorisée » sans personne, c'est-à-dire
     * exactement l'état où la garde d'argent s'efface : une telle ligne est censée n'avoir jamais
     * eu de professionnel. Le départ offrirait alors un contournement en deux temps — libérer, puis
     * attribuer sans contrôle —, et l'encaissement partirait chez quelqu'un qui a quitté la
     * société.
     *
     * La réservation reste donc nommée, et attend une décision humaine.
     */
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

    /**
     * LES DEUX FORMULATIONS DE LA MÊME RÈGLE DOIVENT RENDRE LE MÊME VERDICT.
     *
     * `intervenantId()` répond réservation par réservation, `scopeIntervenantEst()` filtre en SQL.
     * Deux écritures d'une même règle sont exactement ce qui a produit le défaut d'origine : ce
     * test les confronte sur un jeu mélangé, divergences héritées comprises.
     */
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

        // Personne.
        [$personne, $m5] = $this->missionAssigneeA($a);
        Booking::query()->whereKey($personne->id)->update(['employe_id' => null, 'assigned_provider_user_id' => null]);
        Mission::query()->whereKey($m5->id)->update(['lead_provider_user_id' => null, 'lead_employee_id' => null]);

        $toutes = Booking::query()->with('missions')->get();

        foreach ([$a, $b] as $utilisateur) {
            $parLeResolveur = $toutes
                ->filter(fn (Booking $r) => $r->intervenantId() === $utilisateur->id)
                ->pluck('id')->sort()->values()->all();

            $parLeFiltre = Booking::query()->intervenantEst($utilisateur->id)
                ->pluck('id')->sort()->values()->all();

            $this->assertSame(
                $parLeResolveur,
                $parLeFiltre,
                "Le filtre SQL et le résolveur divergent pour l’utilisateur #{$utilisateur->id}.",
            );
        }

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
        $this->assertNull($personne->fresh()->intervenantId());
    }

    /**
     * LA GARDE — pour que l'écart ne se rouvre pas ligne par ligne.
     *
     * La chaîne de repli `employe_id ?? assigned_provider_user_id` était la SIGNATURE du défaut :
     * elle disait « je résous qui intervient », et le faisait sans jamais regarder la mission. Elle
     * ne doit exister qu'à l'endroit qui porte désormais la règle.
     *
     * `InsurancePricingEngine` est la seule exception, et elle est motivée : cette méthode lit la
     * table en direct (`DB::table`), sans modèle, donc sans accès au résolveur — elle en recopie
     * l'ordre de priorité, mission d'abord, et le commentaire le dit.
     */
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
