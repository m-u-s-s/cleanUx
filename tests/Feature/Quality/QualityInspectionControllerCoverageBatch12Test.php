<?php

namespace Tests\Feature\Quality;

use App\Models\MissionQualityInspection;
use App\Models\QualityChecklistItem;
use App\Models\User;
use App\Services\Quality\QualityInspectionService;
use Database\Seeders\QualityChecklistsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * Exercises the provider-facing Quality inspection API controller end-to-end
 * (index / start / show / submitItem / uploadPhoto / submit), focusing on the
 * happy paths and the ValidationException → 422 branches not covered by the
 * IDOR/ownership test.
 */
class QualityInspectionControllerCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    private MissionQualityInspection $inspection;

    private User $ownerProvider;

    private int $missionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QualityChecklistsSeeder::class);
        Config::set('quality.enabled', true);
        Config::set('quality.photo_storage_disk', 'private');

        $scenario = SpineScenario::make()->build();
        $this->ownerProvider = $scenario->provider;
        $this->missionId = (int) $scenario->mission->id;

        $this->inspection = app(QualityInspectionService::class)->start(
            missionId: $this->missionId,
            phase: 'post',
            provider: $scenario->provider,
        );
    }

    public function test_owner_provider_lists_mission_inspections(): void
    {
        Sanctum::actingAs($this->ownerProvider);

        $this->getJson("/api/provider/missions/{$this->missionId}/inspections")
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->inspection->id);
    }

    public function test_owner_provider_starts_inspection(): void
    {
        Sanctum::actingAs($this->ownerProvider);

        $this->postJson("/api/provider/missions/{$this->missionId}/inspections", [
            'phase' => 'post',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('inspection.phase', 'post');
    }

    public function test_start_with_unsupported_phase_returns_validation_error(): void
    {
        Sanctum::actingAs($this->ownerProvider);

        $this->postJson("/api/provider/missions/{$this->missionId}/inspections", [
            'phase' => 'midflight',
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_owner_provider_shows_inspection(): void
    {
        Sanctum::actingAs($this->ownerProvider);

        $this->getJson("/api/provider/inspections/{$this->inspection->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->inspection->id);
    }

    public function test_owner_provider_submits_checklist_item(): void
    {
        Sanctum::actingAs($this->ownerProvider);

        $item = $this->inspection->checklist->items->first();

        $this->putJson("/api/provider/inspections/{$this->inspection->id}/items/{$item->id}", [
            'value' => ['answer' => true],
            'comment' => 'tout est propre',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('item.checklist_item_id', $item->id);
    }

    public function test_submit_item_from_foreign_checklist_returns_validation_error(): void
    {
        Sanctum::actingAs($this->ownerProvider);

        $foreignItem = QualityChecklistItem::query()
            ->where('checklist_id', '!=', $this->inspection->checklist_id)
            ->first();

        $this->assertNotNull($foreignItem, 'Seeder must provide a second checklist.');

        $this->putJson("/api/provider/inspections/{$this->inspection->id}/items/{$foreignItem->id}", [
            'value' => ['answer' => true],
        ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_owner_provider_uploads_photo(): void
    {
        Storage::fake('private');
        Sanctum::actingAs($this->ownerProvider);

        $this->postJson("/api/provider/inspections/{$this->inspection->id}/photos", [
            'photo' => UploadedFile::fake()->create('after.jpg', 120, 'image/jpeg'),
            'photo_type' => 'after',
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('photo.photo_type', 'after');
    }

    public function test_owner_provider_submits_inspection_with_score(): void
    {
        Sanctum::actingAs($this->ownerProvider);

        foreach ($this->inspection->checklist->items as $item) {
            $value = match ($item->item_type) {
                'boolean' => ['answer' => true],
                'rating' => ['rating' => 5],
                'photo' => [],
                default => ['text' => 'ok'],
            };
            app(QualityInspectionService::class)->submitItem($this->inspection, $item, $value);
        }

        $this->postJson("/api/provider/inspections/{$this->inspection->id}/submit")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('inspection.status', MissionQualityInspection::STATUS_SUBMITTED);
    }
}
