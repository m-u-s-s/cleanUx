<?php

namespace Tests\Feature\Simulation;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;
use App\Support\Domain\MissionStatus;
use App\Support\Quality\QualityInspectionAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * LES SIX SOUS-RÔLES QUE LE PARCOURS COMPLET NE TRAVERSAIT PAS.
 *
 * `ParcoursCompletTest` joue l'intervention entière pour quatre couples client/prestataire, mais
 * côté exécution il ne connaît que deux personnes : le travailleur et le renfort. Une société en
 * déclare ONZE. Six d'entre elles — coordinateur, directeur des opérations, responsable qualité,
 * finance, lecteur, demandeur — n'apparaissaient dans AUCUN parcours de mission.
 *
 * ELLES ÉTAIENT TESTÉES, MAIS AU MAUVAIS NIVEAU. La matrice de permissions de `PermissionService`
 * est vérifiée clé par clé, et elle est juste. Or les deux fuites trouvées cette semaine vivaient
 * précisément AU-DESSUS d'un service juste : un écran qui n'appelle pas la garde, un endpoint qui
 * la contourne. Une clé accordée ne prouve pas que le geste aboutit, et une clé refusée ne prouve
 * pas que le geste est bloqué.
 *
 * D'OÙ CE FICHIER : pour chaque sous-rôle, on tente le GESTE, sur la surface réelle, et on regarde
 * ce qui se passe. Deux questions à chaque fois, et il faut les deux — « ce rôle peut-il faire ce
 * qu'on attend de lui ? » et « ne peut-il rien faire de plus ? ». Une porte fermée qui devait être
 * ouverte est un rôle inutilisable ; une porte ouverte qui devait être fermée est une fuite.
 */
class ParcoursSousRolesTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $contexte;

    private OrganizationAccount $societe;

    private User $travailleur;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contexte = $this->createCoverageContext();

        $this->societe = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $this->travailleur = $this->membre(OrganizationRole::WORKER);
        $this->mission = $this->missionAssignee($this->travailleur);
    }

    private function membre(OrganizationRole $role): User
    {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $this->societe->id,
            'organization_account_id' => $this->societe->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $this->societe->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    private function missionAssignee(User $intervenant): Mission
    {
        $client = User::factory()->client()->create();

        $reservation = Booking::factory()
            ->forStructuredContext(
                $this->contexte['service'],
                $this->contexte['zone'],
                $this->contexte['postalCode'],
            )
            ->create([
                'client_id' => $client->id,
                'customer_user_id' => $client->id,
                'status' => 'confirme',
                'devis_estime' => 150,
                'date' => now()->addDay()->toDateString(),
                'heure' => '09:00:00',
                'assigned_provider_organization_id' => $this->societe->id,
            ]);

        $mission = $reservation->missions()->latest('id')->firstOrFail();
        $mission->forceFill([
            'provider_organization_id' => $this->societe->id,
            'lead_provider_user_id' => $intervenant->id,
            'lead_employee_id' => $intervenant->id,
            'status' => MissionStatus::ASSIGNED,
            'planned_start_at' => now()->addDay(),
        ])->save();

        MissionAssignment::query()->updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $intervenant->id],
            ['role_on_mission' => 'lead', 'assignment_status' => 'assigned', 'assigned_at' => now()],
        );

        return $mission->fresh();
    }

    /** Tente de confier la mission à quelqu'un d'autre, par l'API réelle de l'espace société. */
    private function tenterUneReassignation(User $acteur, User $destinataire): TestResponse
    {
        return $this->actingAs($acteur, 'sanctum')
            ->postJson("/api/provider/company/missions/{$this->mission->id}/assign", [
                'user_id' => $destinataire->id,
            ]);
    }

    /** Tente de conduire la mission sur le terrain, par l'API prestataire réelle. */
    private function tenterDePartirEnMission(User $acteur): TestResponse
    {
        return $this->actingAs($acteur, 'sanctum')
            ->postJson("/api/provider/missions/{$this->mission->id}/start");
    }

    /**
     * LE TÉMOIN — sans lui, tous les refus de ce fichier ne prouveraient rien.
     *
     * Chaque test ci-dessous s'appuie sur un 403 pour affirmer qu'un rôle est tenu à l'écart. Mais
     * un 403 peut venir d'ailleurs : une route mal montée, un jeton refusé, un intergiciel qui
     * ferme la porte à tout le monde. Dans ce cas les neuf tests passeraient au vert en ne mesurant
     * rien d'autre qu'une panne.
     *
     * Ce témoin-ci exige que la MÊME porte s'ouvre pour la personne assignée. Il faut les deux :
     * la porte s'ouvre pour qui de droit, elle reste fermée pour les autres. Le premier sans le
     * second est une fuite, le second sans le premier est une illusion.
     */
    public function test_temoin_la_personne_assignee_peut_bien_partir_en_mission(): void
    {
        $reponse = $this->tenterDePartirEnMission($this->travailleur);

        $this->assertNotSame(
            403,
            $reponse->getStatusCode(),
            'La porte est fermée à tout le monde : les refus mesurés ailleurs dans ce fichier ne prouvent rien.',
        );

        $this->assertSame(
            MissionStatus::EN_ROUTE,
            $this->mission->fresh()->status,
            'La mission n’est pas passée en route : la surface interrogée n’est pas celle qu’on croit.',
        );
    }

    /**
     * MÊME TÉMOIN POUR LA RÉASSIGNATION.
     *
     * Le propriétaire de la société doit pouvoir réassigner : si lui-même se voyait refuser, les
     * 403 opposés au responsable qualité, à la finance et au lecteur ne diraient rien de leur rôle.
     */
    public function test_temoin_le_proprietaire_peut_bien_reassigner(): void
    {
        $proprietaire = $this->membre(OrganizationRole::OWNER);
        $remplacant = $this->membre(OrganizationRole::WORKER);

        $this->tenterUneReassignation($proprietaire, $remplacant)->assertOk();
        $this->assertSame($remplacant->id, $this->mission->fresh()->intervenantId());
    }

    // ──────────────────────────────────────────────────────
    // Coordinateur — il répartit, il n'exécute pas
    // ──────────────────────────────────────────────────────

    public function test_le_coordinateur_reassigne_mais_ne_part_pas_en_mission(): void
    {
        $coordinateur = $this->membre(OrganizationRole::DISPATCHER);
        $remplacant = $this->membre(OrganizationRole::WORKER);

        $this->tenterUneReassignation($coordinateur, $remplacant)->assertOk();

        $this->assertSame(
            $remplacant->id,
            $this->mission->fresh()->intervenantId(),
            'Le coordinateur ne parvient pas à réassigner, alors que c’est son métier.',
        );

        /*
         * ET IL NE CONDUIT PAS LA MISSION LUI-MÊME. `missions.assign` ouvre la répartition, pas le
         * terrain : un coordinateur qui pourrait se déclarer en route fausserait la présence, le
         * suivi de trajet et, au bout, le versement.
         */
        $this->tenterDePartirEnMission($coordinateur)->assertForbidden();
    }

    // ──────────────────────────────────────────────────────
    // Directeur des opérations — la même capacité, plus large
    // ──────────────────────────────────────────────────────

    public function test_le_directeur_des_operations_reassigne_mais_ne_part_pas_en_mission(): void
    {
        $directeur = $this->membre(OrganizationRole::OPERATIONS_MANAGER);
        $remplacant = $this->membre(OrganizationRole::WORKER);

        $this->tenterUneReassignation($directeur, $remplacant)->assertOk();
        $this->assertSame($remplacant->id, $this->mission->fresh()->intervenantId());

        $this->tenterDePartirEnMission($directeur)->assertForbidden();
    }

    // ──────────────────────────────────────────────────────
    // Responsable qualité — il contrôle, il ne redistribue pas
    // ──────────────────────────────────────────────────────

    /**
     * LE RESPONSABLE QUALITÉ NE DOIT NI RÉASSIGNER NI PARTIR EN MISSION.
     *
     * Sa matrice lui donne `missions.view_all` et `missions.quality` : regarder et contrôler. Pas
     * `missions.assign` — décider qui va où n'est pas son métier, et le lui laisser reviendrait à
     * ce que le contrôleur choisisse le contrôlé.
     */
    public function test_le_responsable_qualite_ne_redistribue_pas_les_missions(): void
    {
        $qualite = $this->membre(OrganizationRole::QUALITY_MANAGER);
        $remplacant = $this->membre(OrganizationRole::WORKER);

        $this->tenterUneReassignation($qualite, $remplacant)->assertForbidden();
        $this->tenterDePartirEnMission($qualite)->assertForbidden();

        $this->assertSame(
            $this->travailleur->id,
            $this->mission->fresh()->intervenantId(),
            'La mission a changé de main sous l’action du responsable qualité.',
        );
    }

    /**
     * ET L'INSPECTION QUALITÉ RESTE FERMÉE À QUI N'EST PAS INTERVENU — Y COMPRIS À LUI.
     *
     * C'est le résultat le plus contre-intuitif de ce fichier, et il mérite d'être écrit noir sur
     * blanc plutôt que découvert un jour en production : `QualityInspectionAccess` n'admet que le
     * CLIENT et les INTERVENANTS de la mission. Un responsable qualité qui n'y est pas assigné n'a
     * donc aucun accès aux inspections de sa propre société, malgré `missions.quality`.
     *
     * On fige le comportement réel plutôt que celui qu'on suppose. Si c'est un choix — le contrôle
     * qualité passe par l'espace société, pas par l'objet inspection —, ce test le documente. Si
     * c'en est un défaut, il devient visible et discutable, ce qu'il n'était pas.
     */
    public function test_l_inspection_qualite_n_est_ouverte_qu_aux_intervenants(): void
    {
        $qualite = $this->membre(OrganizationRole::QUALITY_MANAGER);

        $this->assertTrue(
            QualityInspectionAccess::providerCanAccessMission($this->travailleur, (int) $this->mission->id),
            'Celui qui exécute la mission doit pouvoir remplir son inspection.',
        );

        $this->assertFalse(
            QualityInspectionAccess::providerCanAccessMission($qualite, (int) $this->mission->id),
            'Le périmètre de l’inspection s’est élargi : vérifier que c’est voulu.',
        );
    }

    // ──────────────────────────────────────────────────────
    // Finance, lecteur, demandeur — aucun pouvoir sur le terrain
    // ──────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: OrganizationRole}>
     */
    public static function rolesSansPouvoirSurLeTerrain(): array
    {
        return [
            'finance' => [OrganizationRole::FINANCE],
            'lecteur' => [OrganizationRole::VIEWER],
            'demandeur' => [OrganizationRole::REQUESTER],
        ];
    }

    /**
     * NI RÉASSIGNER, NI PARTIR — pour les trois rôles qui n'ont rien à faire sur une mission.
     *
     * Le lecteur est le cas le plus révélateur : sa matrice ne lui donne AUCUNE clé d'écriture. Un
     * rôle nommé « lecteur » qui parviendrait à déplacer une intervention serait une contradiction
     * pure, et le genre de chose que personne ne pense à essayer.
     */
    #[DataProvider('rolesSansPouvoirSurLeTerrain')]
    public function test_les_roles_administratifs_ne_touchent_pas_a_la_mission(OrganizationRole $role): void
    {
        $acteur = $this->membre($role);
        $remplacant = $this->membre(OrganizationRole::WORKER);

        $this->tenterUneReassignation($acteur, $remplacant)->assertForbidden();
        $this->tenterDePartirEnMission($acteur)->assertForbidden();

        $this->assertSame(
            $this->travailleur->id,
            $this->mission->fresh()->intervenantId(),
            "Le rôle « {$role->value} » a modifié l’intervenant de la mission.",
        );
    }

    /**
     * LE DEMANDEUR COMMANDE — c'est tout ce qu'il fait, et il doit pouvoir le faire.
     *
     * Le contre-test du précédent : refuser à un demandeur le seul geste de son rôle le rendrait
     * inutile, et ce serait aussi grave qu'une porte laissée ouverte. `bookings.create` est la
     * seule clé d'écriture de sa matrice.
     */
    public function test_le_demandeur_peut_commander_mais_pas_approuver(): void
    {
        $demandeur = $this->membre(OrganizationRole::REQUESTER);
        $permissions = app(PermissionService::class);

        $this->assertTrue(
            $permissions->can($demandeur, 'bookings.create', $this->societe),
            'Un demandeur qui ne peut pas commander n’a plus de rôle.',
        );

        foreach (['bookings.approve', 'missions.assign', 'finance.view'] as $interdite) {
            $this->assertFalse(
                $permissions->can($demandeur, $interdite, $this->societe),
                "Le demandeur dispose de « {$interdite} », qui dépasse son rôle.",
            );
        }
    }

    /**
     * LE RÔLE NE TRAVERSE PAS LES SOCIÉTÉS.
     *
     * Un coordinateur est coordinateur CHEZ LUI. Être investi quelque part n'ouvre rien ailleurs —
     * c'est la garde que `ReassignmentPolicy` évalue sur l'organisation de la MISSION, jamais sur
     * celle de qui regarde. Sans elle, il suffirait de créer sa propre société pour se donner les
     * clés sur les missions des autres.
     */
    public function test_un_coordinateur_d_une_autre_societe_ne_peut_rien(): void
    {
        $voisine = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $etranger = User::factory()->employe()->create([
            'current_organization_id' => $voisine->id,
            'organization_account_id' => $voisine->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $voisine->id,
            'user_id' => $etranger->id,
            'role' => OrganizationRole::DISPATCHER->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $remplacant = $this->membre(OrganizationRole::WORKER);

        $reponse = $this->tenterUneReassignation($etranger->fresh(), $remplacant);
        $this->assertContains($reponse->getStatusCode(), [403, 404]);

        $this->assertSame($this->travailleur->id, $this->mission->fresh()->intervenantId());
    }
}
