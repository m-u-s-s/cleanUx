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

/** LES SIX SOUS-RÔLES QUE LE PARCOURS COMPLET NE TRAVERSAIT PAS. */
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

    /** LE TÉMOIN — sans lui, tous les refus de ce fichier ne prouveraient rien. */
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

    /** MÊME TÉMOIN POUR LA RÉASSIGNATION. */
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

        // ET IL NE CONDUIT PAS LA MISSION LUI-MÊME.
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

    /** LE RESPONSABLE QUALITÉ NE DOIT NI RÉASSIGNER NI PARTIR EN MISSION. */
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

    /** ET L'INSPECTION QUALITÉ RESTE FERMÉE À QUI N'EST PAS INTERVENU — Y COMPRIS À LUI. */
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

    /** NI RÉASSIGNER, NI PARTIR — pour les trois rôles qui n'ont rien à faire sur une mission. */
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

    /** LE DEMANDEUR COMMANDE — c'est tout ce qu'il fait, et il doit pouvoir le faire. */
    public function test_le_demandeur_peut_commander_mais_pas_approuver(): void
    {
        $demandeur = $this->membre(OrganizationRole::REQUESTER);
        $permissions = app(PermissionService::class);

        $this->assertTrue(
            $permissions->can($demandeur, 'bookings.create', $this->societe),
            'Un demandeur qui ne peut pas commander n’a plus de rôle.',
        );

        // Les trois capacites relevees ensemble : un role trop large les accorde TOUTES, et la
        // liste dit s'il s'agit d'un depassement ponctuel ou d'un role mal borne.
        $accordees = array_values(array_filter(
            ['bookings.approve', 'missions.assign', 'finance.view'],
            fn (string $c) => $permissions->can($demandeur, $c, $this->societe),
        ));

        $this->assertSame([], $accordees, 'Le demandeur dispose de ces capacites, qui depassent son role.');
    }

    /** LE RÔLE NE TRAVERSE PAS LES SOCIÉTÉS. Un coordinateur est coordinateur CHEZ LUI. */
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
