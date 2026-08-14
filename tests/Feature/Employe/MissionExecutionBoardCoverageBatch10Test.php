<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\MissionExecutionBoard;
use App\Models\MissionAssignment;
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
        $this->assertGreaterThan(0, MissionChecklistItem::query()->count());
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

        $item = MissionChecklistItem::query()
            ->whereHas('checklist', fn ($q) => $q->where('mission_id', $scenario['mission']->id))
            ->firstOrFail();

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
