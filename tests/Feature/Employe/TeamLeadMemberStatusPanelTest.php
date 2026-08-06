<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\TeamLeadOperationsCenter;
use App\Models\Booking;
use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\MissionBatchDay;
use App\Models\MissionTaskSegment;
use App\Models\MissionTaskSegmentAssignment;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE PANNEAU « STATUT MEMBRE PAR MEMBRE » NE POUVAIT PAS S'AFFICHER.
 *
 * `member-status-panel.blade.php` parcourt `$selectedSegment->assignments`, et
 * `TeamLeadOperationsCenter` chargeait `MissionTaskSegment::with(['assignments.user', 'memberStatuses'])`.
 * Or `MissionTaskSegment` n'exposait NI l'une NI l'autre : l'eager-load levait une
 * `RelationNotFoundException`, et l'écran entier tombait dès qu'un segment existait.
 *
 * CE QUI EXISTAIT DÉJÀ, vérifié avant d'écrire — et qui corrige mon propre diagnostic de la veille,
 * où j'avais conclu trop vite que « la fonctionnalité était à reconstruire » :
 *
 *   - les tables `mission_task_segment_assignments` et `mission_member_statuses` ;
 *   - leurs modèles, avec `user()`, `segment()`, `segmentAssignment()`, `memberStatuses()` ;
 *   - `TeamLeadOperationsCenter::updateSelectedMemberStatus()` et le service qui l'exécute.
 *
 * Seules manquaient les DEUX relations inverses sur le segment. J'avais lu le seul modèle segment
 * et généralisé — la leçon est la même que pour `MembersAccess` en phase 0.
 *
 * SECOND DÉFAUT, du même motif que les canaux : la liste des lots est bien limitée aux équipes dont
 * on est chef, mais `updateSelectedMemberStatus()` faisait `findOrFail()` sur un identifiant venu du
 * client, et le service n'autorise rien. Un chef d'équipe pouvait donc écrire le statut d'un membre
 * d'une AUTRE équipe.
 */
class TeamLeadMemberStatusPanelTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: MissionTaskSegment, 2: MissionTaskSegmentAssignment} */
    private function equipeAvecSegment(string $nomEquipier = 'Camille Ouvrier'): array
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $chef = User::factory()->create(['role' => 'employe']);
        $equipier = User::factory()->create(['name' => $nomEquipier, 'role' => 'employe']);

        $equipe = FieldTeam::create([
            'organization_account_id' => $org->id,
            'name' => 'Agence '.uniqid(),
            'slug' => 'agence-'.uniqid(),
            'status' => 'active',
            'team_lead_user_id' => $chef->id,
        ]);

        $mission = Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'planned_start_at' => now(),
        ]);

        /*
         * LE CHEF EST DÉSIGNÉ PAR LE PIVOT, PAS PAR LE LOT.
         *
         * `managedBatches()` accepte deux chemins : `mission_batches.team_lead_user_id`, ou
         * l'appartenance à l'équipe avec `is_team_lead`. Le PREMIER est mort — la colonne existe et
         * est lue, mais elle est absente de `$fillable` et aucune ligne du dépôt ne la renseigne :
         * toute écriture par `create()` est silencieusement perdue. On emprunte donc le chemin que
         * la production utilise réellement.
         */
        FieldTeamMember::create([
            'field_team_id' => $equipe->id,
            'user_id' => $chef->id,
            'is_team_lead' => true,
            'is_active' => true,
            'status' => 'active',
        ]);

        $lot = MissionBatch::create([
            'field_team_id' => $equipe->id,
            'name' => 'Lot du jour',
            'starts_on' => now()->toDateString(),
            'status' => 'active',
        ]);

        // `currentSegments()` remonte les segments par le JOUR du lot, pas par le lot direct :
        // un segment sans `mission_batch_day_id` reste invisible de l'écran.
        $jour = MissionBatchDay::create([
            'mission_batch_id' => $lot->id,
            'service_date' => now()->toDateString(),
            'status' => 'planned',
        ]);

        $segment = MissionTaskSegment::create([
            'mission_batch_id' => $lot->id,
            'mission_batch_day_id' => $jour->id,
            'mission_id' => $mission->id,
            'field_team_id' => $equipe->id,
            'title' => 'Nettoyage hall',
            'service_date' => now()->toDateString(),
            'status' => 'planned',
        ]);

        $affectation = MissionTaskSegmentAssignment::create([
            'mission_task_segment_id' => $segment->id,
            'mission_id' => $mission->id,
            'field_team_id' => $equipe->id,
            'user_id' => $equipier->id,
            'assignment_role' => 'operator',
            'status' => 'assigned',
        ]);

        return [$chef, $segment, $affectation];
    }

    #[Test]
    public function le_panneau_affiche_les_affectations_du_segment(): void
    {
        [$chef, , $affectation] = $this->equipeAvecSegment();

        Livewire::actingAs($chef)
            ->test(TeamLeadOperationsCenter::class)
            ->assertOk()
            ->assertSee($affectation->user->name);
    }

    #[Test]
    public function le_segment_expose_ses_affectations_et_ses_statuts(): void
    {
        [, $segment, $affectation] = $this->equipeAvecSegment();

        // Les deux relations que l'eager-load du composant réclamait sans qu'elles existent.
        $this->assertCount(1, $segment->assignments);
        $this->assertSame($affectation->id, $segment->assignments->first()->id);
        $this->assertCount(0, $segment->memberStatuses);
    }

    #[Test]
    public function un_chef_ne_met_pas_a_jour_le_membre_d_une_autre_equipe(): void
    {
        [$chef] = $this->equipeAvecSegment();
        [, , $affectationEtrangere] = $this->equipeAvecSegment('Employé Concurrent');

        Livewire::actingAs($chef)
            ->test(TeamLeadOperationsCenter::class)
            ->set('progressPercent', 100)
            ->call('updateSelectedMemberStatus', $affectationEtrangere->id)
            ->assertForbidden();

        $this->assertDatabaseMissing('mission_member_statuses', [
            'segment_assignment_id' => $affectationEtrangere->id,
        ]);
    }

    #[Test]
    public function un_chef_met_a_jour_le_membre_de_sa_propre_equipe(): void
    {
        [$chef, , $affectation] = $this->equipeAvecSegment();

        Livewire::actingAs($chef)
            ->test(TeamLeadOperationsCenter::class)
            ->set('progressPercent', 60)
            ->set('minutesSpent', 45)
            ->call('updateSelectedMemberStatus', $affectation->id);

        $this->assertDatabaseHas('mission_member_statuses', [
            'segment_assignment_id' => $affectation->id,
            'progress_percent' => 60,
        ]);
    }
}
