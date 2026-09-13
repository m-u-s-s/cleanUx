<?php

namespace Tests\Feature\Security;

use App\Livewire\Chatbot\AssistantWidget;
use App\Livewire\Client\ClientKybOnboarding;
use App\Livewire\Client\Templates\RecurringTemplatesGallery;
use App\Livewire\Employe\TeamLeadOperationsCenter;
use App\Models\Booking;
use App\Models\BusinessEntity;
use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\MissionBatchDay;
use App\Models\MissionTaskSegment;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\RecurringTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UNE PROPRIÉTÉ PUBLIQUE LIVEWIRE EST UNE GARDE RÉVERSIBLE : le navigateur la retourne par `$set`.
 * Quatre écrans lisaient un identifiant venu du client sans le borner.
 *
 * Deux remèdes, et ils ne sont PAS interchangeables : `#[Locked]` quand la valeur ne vient jamais
 * d'une saisie, une garde d'appartenance côté serveur quand l'utilisateur la choisit vraiment.
 */
class IdentifiantVenantDuNavigateurTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: MissionTaskSegment} */
    private function equipeAvecSegment(): array
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $chef = User::factory()->create(['role' => 'employe']);

        $equipe = FieldTeam::create([
            'organization_account_id' => $org->id,
            'name' => 'Agence '.uniqid(),
            'slug' => 'agence-'.uniqid(),
            'status' => 'active',
            'team_lead_user_id' => $chef->id,
        ]);

        FieldTeamMember::create([
            'field_team_id' => $equipe->id,
            'user_id' => $chef->id,
            'is_team_lead' => true,
            'is_active' => true,
            'status' => 'active',
        ]);

        $mission = Mission::create([
            'booking_id' => Booking::factory()->create()->id,
            'status' => 'planned',
            'provider_organization_id' => $org->id,
            'planned_start_at' => now(),
        ]);

        $lot = MissionBatch::create([
            'field_team_id' => $equipe->id,
            'name' => 'Lot du jour',
            'starts_on' => now()->toDateString(),
            'status' => 'active',
        ]);

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

        return [$chef, $segment];
    }

    /**
     * LE TÉMOIN POSITIF DU CHEF D'ÉQUIPE : sur SON segment, avec un membre de SON équipe,
     * l'affectation aboutit. Sans lui, le refus ci-dessous passerait au vert si plus personne
     * ne pouvait affecter quoi que ce soit.
     */
    #[Test]
    public function un_chef_affecte_bien_un_membre_de_son_equipe_sur_son_segment(): void
    {
        [$chef, $segment] = $this->equipeAvecSegment();

        $equipier = User::factory()->create(['role' => 'employe']);
        FieldTeamMember::create([
            'field_team_id' => $segment->field_team_id,
            'user_id' => $equipier->id,
            'is_team_lead' => false,
            'is_active' => true,
            'status' => 'active',
        ]);

        Livewire::actingAs($chef)
            ->test(TeamLeadOperationsCenter::class)
            ->set('selectedSegmentId', $segment->id)
            ->set('selectedAssigneeId', $equipier->id)
            ->call('assignSelectedSegment')
            ->assertOk();

        $this->assertDatabaseHas('mission_task_segment_assignments', [
            'mission_task_segment_id' => $segment->id,
            'user_id' => $equipier->id,
        ]);
    }

    #[Test]
    public function un_chef_n_affecte_pas_le_segment_d_une_autre_societe(): void
    {
        [$chef] = $this->equipeAvecSegment();
        [, $segmentDuConcurrent] = $this->equipeAvecSegment();

        Livewire::actingAs($chef)
            ->test(TeamLeadOperationsCenter::class)
            ->set('selectedSegmentId', $segmentDuConcurrent->id)
            ->set('selectedAssigneeId', $chef->id)
            ->call('assignSelectedSegment')
            ->assertForbidden();
    }

    #[Test]
    public function un_chef_n_affecte_pas_quelqu_un_qui_n_est_pas_de_l_equipe(): void
    {
        [$chef, $segment] = $this->equipeAvecSegment();
        $etranger = User::factory()->create(['role' => 'employe']);

        Livewire::actingAs($chef)
            ->test(TeamLeadOperationsCenter::class)
            ->set('selectedSegmentId', $segment->id)
            ->set('selectedAssigneeId', $etranger->id)
            ->call('assignSelectedSegment')
            ->assertForbidden();
    }

    #[Test]
    public function un_chef_ne_demande_pas_de_renfort_sur_le_segment_d_un_autre(): void
    {
        [$chef] = $this->equipeAvecSegment();
        [, $segmentDuConcurrent] = $this->equipeAvecSegment();

        Livewire::actingAs($chef)
            ->test(TeamLeadOperationsCenter::class)
            ->set('selectedSegmentId', $segmentDuConcurrent->id)
            ->set('reinforcementReason', 'peu importe')
            ->call('requestReinforcement')
            ->assertForbidden();
    }

    #[Test]
    public function un_chef_ne_cloture_pas_l_intervention_d_un_autre(): void
    {
        [$chef] = $this->equipeAvecSegment();
        [, $segmentDuConcurrent] = $this->equipeAvecSegment();

        Livewire::actingAs($chef)
            ->test(TeamLeadOperationsCenter::class)
            ->call('closeSelectedBatchMission', $segmentDuConcurrent->mission_id)
            ->assertForbidden();
    }

    /** LE TÉMOIN POSITIF DU KYB : le propriétaire retrouve bien SON dossier au montage. */
    #[Test]
    public function le_proprietaire_retrouve_son_propre_dossier_kyb(): void
    {
        $client = User::factory()->create();
        $entite = BusinessEntity::factory()->create(['owner_user_id' => $client->id]);

        Livewire::actingAs($client)
            ->test(ClientKybOnboarding::class)
            ->assertSet('entityId', $entite->id);
    }

    #[Test]
    public function le_dossier_kyb_ne_se_designe_pas_depuis_le_navigateur(): void
    {
        $victime = User::factory()->create();
        $entiteVictime = BusinessEntity::factory()->create(['owner_user_id' => $victime->id]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs(User::factory()->create())
            ->test(ClientKybOnboarding::class)
            ->set('entityId', $entiteVictime->id);
    }

    #[Test]
    public function la_conversation_d_assistant_ne_se_designe_pas_depuis_le_navigateur(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs(User::factory()->create())
            ->test(AssistantWidget::class)
            ->set('conversationId', 7);
    }

    /** @return array{0: User, 1: OrganizationSite, 2: RecurringTemplate} */
    private function societeAvecLocalEtGabarit(): array
    {
        $org = OrganizationAccount::factory()->create();
        $site = OrganizationSite::factory()->create(['organization_account_id' => $org->id]);
        $client = User::factory()->create(['organization_account_id' => $org->id]);
        $gabarit = RecurringTemplate::factory()->create(['is_system' => true, 'is_active' => true]);

        return [$client, $site, $gabarit];
    }

    /** LE TÉMOIN POSITIF : sur SON local, la série se crée bien. */
    #[Test]
    public function une_recurrence_se_pose_bien_sur_un_local_de_sa_societe(): void
    {
        [$client, $site, $gabarit] = $this->societeAvecLocalEtGabarit();

        Livewire::actingAs($client)
            ->test(RecurringTemplatesGallery::class)
            ->call('openApplyModal', $gabarit->id)
            ->set('selectedSiteId', $site->id)
            ->call('applyTemplate')
            ->assertOk();

        $this->assertDatabaseHas('recurring_booking_series', [
            'customer_user_id' => $client->id,
            'organization_site_id' => $site->id,
        ]);
    }

    /** La liste du `render()` filtre l'affichage ; elle ne gardait pas l'écriture. */
    #[Test]
    public function une_recurrence_ne_se_pose_pas_sur_le_local_d_une_autre_societe(): void
    {
        [$client, , $gabarit] = $this->societeAvecLocalEtGabarit();
        [, $siteDuConcurrent] = $this->societeAvecLocalEtGabarit();

        Livewire::actingAs($client)
            ->test(RecurringTemplatesGallery::class)
            ->call('openApplyModal', $gabarit->id)
            ->set('selectedSiteId', $siteDuConcurrent->id)
            ->call('applyTemplate')
            ->assertForbidden();

        $this->assertDatabaseMissing('recurring_booking_series', [
            'organization_site_id' => $siteDuConcurrent->id,
        ]);
    }
}
