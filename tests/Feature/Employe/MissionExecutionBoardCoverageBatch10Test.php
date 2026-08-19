<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\MissionExecutionBoard;
use App\Models\MissionAssignment;
use App\Models\MissionChecklist;
use App\Models\MissionChecklistItem;
use App\Models\MissionMedia;
use App\Models\User;
use App\Services\Missions\MissionChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesMissionPortalFixtures;
use Tests\TestCase;

class MissionExecutionBoardCoverageBatch10Test extends TestCase
{
    use CreatesMissionPortalFixtures;
    use RefreshDatabase;

    /**
     * LA LISTE NAÎT VIDE, ET C'EST VOULU.
     *
     * Ce test exigeait des tâches dès le montage : `ensureChecklist()` posait alors les six lignes
     * d'un gabarit, toutes obligatoires. Le prestataire cochait donc six cases que personne ne lui
     * avait demandées, pendant que ce que le CLIENT voulait n'existait nulle part.
     *
     * Le gabarit est devenu une SUGGESTION, et la liste appartient au client. L'assertion change
     * donc de sens : le porte-liste existe, il est vide, et une mission sans demande du client ne
     * bloque personne. Le témoin positif est deux lignes plus bas — dès qu'une tâche existe, elle
     * bloque bien.
     */
    public function test_lead_employee_can_mount_board_and_checklist_is_seeded(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'assigned']);

        $this->actingAs($scenario['employee']);

        Livewire::test(MissionExecutionBoard::class, ['mission' => $scenario['mission']])
            ->assertOk()
            ->assertSet('successMessage', null)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('mission_checklists', [
            'mission_id' => $scenario['mission']->id,
        ]);
        $this->assertSame(0, MissionChecklistItem::query()->count(), 'la liste naît vide : elle appartient au client');

        // LE TÉMOIN : une tâche demandée par le client apparaît bien, et elle est obligatoire.
        $tache = $this->tachePosee($scenario['mission']->id);

        $this->assertTrue($tache->is_required);
        $this->assertSame(1, MissionChecklistItem::query()->count());
    }

    /**
     * Pose une tâche comme le client la poserait, et rend la ligne.
     *
     * Directement par le modèle : la fenêtre d'édition et les droits du client ont leurs propres
     * tests. Ce fichier-ci porte sur ce que le TABLEAU DE BORD fait d'une tâche existante.
     */
    private function tachePosee(int $missionId): MissionChecklistItem
    {
        $checklist = MissionChecklist::query()->where('mission_id', $missionId)->firstOrFail();

        return MissionChecklistItem::query()->create([
            'mission_checklist_id' => $checklist->id,
            'label' => 'Nettoyer la hotte',
            'item_type' => 'task',
            'is_required' => true,
            'status' => MissionChecklistService::A_FAIRE,
            'sort_order' => 1,
            'source' => 'client',
        ]);
    }

    /**
     * Ce test figeait `completed` / `pending` — c'est-à-dire exactement le défaut.
     *
     * La colonne déclare son vocabulaire dans sa propre migration (« todo, done ») et c'est `done`
     * que lit la porte de clôture. Cet écran écrivait `completed` : un prestataire pouvait cocher
     * ses six tâches, lire 100 %, et ne jamais pouvoir terminer sa mission. Le test passait au vert
     * parce qu'il vérifiait que l'écran écrit ce que l'écran écrit, sans jamais demander si
     * quelqu'un d'autre le comprenait.
     */
    public function test_toggle_checklist_item_marks_done_then_todo(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'assigned']);
        $this->actingAs($scenario['employee']);

        $component = Livewire::test(MissionExecutionBoard::class, ['mission' => $scenario['mission']]);

        // La liste naît vide depuis que le client la tient : on pose la tâche que le tableau de
        // bord doit savoir cocher, au lieu d'attendre un gabarit qui n'existe plus.
        $item = $this->tachePosee($scenario['mission']->id);

        $component
            ->call('toggleChecklistItem', $item->id)
            ->assertSet('successMessage', 'Checklist mise à jour.');

        $this->assertDatabaseHas('mission_checklist_items', [
            'id' => $item->id,
            'status' => MissionChecklistService::FAITE,
            'completed_by_user_id' => $scenario['employee']->id,
        ]);

        $component->call('toggleChecklistItem', $item->id);

        $this->assertDatabaseHas('mission_checklist_items', [
            'id' => $item->id,
            'status' => MissionChecklistService::A_FAIRE,
            'completed_by_user_id' => null,
        ]);
    }

    public function test_upload_before_photos_persists_media(): void
    {
        Storage::fake('private');

        $scenario = $this->createMissionPortalContext(['status' => 'assigned']);
        $this->actingAs($scenario['employee']);

        $file = UploadedFile::fake()->create('before.jpg', 100, 'image/jpeg');

        Livewire::test(MissionExecutionBoard::class, ['mission' => $scenario['mission']])
            ->set('beforePhotos', [$file])
            ->call('uploadBeforePhotos')
            ->assertSet('successMessage', 'Photos avant ajoutées.')
            ->assertSet('beforePhotos', []);

        $this->assertDatabaseHas('mission_media', [
            'mission_id' => $scenario['mission']->id,
            'media_type' => 'before_photo',
            'uploaded_by_user_id' => $scenario['employee']->id,
        ]);
    }

    public function test_upload_after_photos_persists_media(): void
    {
        Storage::fake('private');

        $scenario = $this->createMissionPortalContext(['status' => 'assigned']);
        $this->actingAs($scenario['employee']);

        $file = UploadedFile::fake()->create('after.jpg', 120, 'image/jpeg');

        Livewire::test(MissionExecutionBoard::class, ['mission' => $scenario['mission']])
            ->set('afterPhotos', [$file])
            ->call('uploadAfterPhotos')
            ->assertSet('successMessage', 'Photos après ajoutées.')
            ->assertSet('afterPhotos', []);

        $this->assertEquals(
            1,
            MissionMedia::query()
                ->where('mission_id', $scenario['mission']->id)
                ->where('media_type', 'after_photo')
                ->count()
        );
    }

    public function test_assigned_member_via_assignment_can_mount(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'assigned']);

        $member = User::factory()->employe()->create([
            'is_active' => true,
            'status' => 'active',
        ]);

        MissionAssignment::factory()->member()->create([
            'mission_id' => $scenario['mission']->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($member);

        Livewire::test(MissionExecutionBoard::class, ['mission' => $scenario['mission']])
            ->assertOk();
    }

    public function test_non_assigned_employee_is_forbidden(): void
    {
        $scenario = $this->createMissionPortalContext(['status' => 'assigned']);

        $stranger = User::factory()->employe()->create([
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->actingAs($stranger);

        Livewire::test(MissionExecutionBoard::class, ['mission' => $scenario['mission']])
            ->assertForbidden();
    }
}
