<?php

namespace Tests\Feature\Quality;

use App\Models\MissionQualityInspection;
use App\Models\User;
use App\Services\Quality\QualityInspectionService;
use Database\Seeders\QualityChecklistsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * C1 — IDOR on Quality/Inspection. Any authenticated user could previously read or mutate
 * another user's inspection by enumerating ids. These tests assert ownership is enforced at
 * the API boundary for both the client and provider controllers.
 */
class QualityInspectionIdorTest extends TestCase
{
    use RefreshDatabase;

    private MissionQualityInspection $inspection;

    private User $ownerClient;

    private User $ownerProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QualityChecklistsSeeder::class);
        Config::set('quality.enabled', true);

        $scenario = SpineScenario::make()->build();
        $this->ownerClient = $scenario->client;
        $this->ownerProvider = $scenario->provider;

        $this->inspection = app(QualityInspectionService::class)->start(
            missionId: (int) $scenario->mission->id,
            phase: 'post',
            provider: $scenario->provider,
        );
        $this->inspection->forceFill(['status' => MissionQualityInspection::STATUS_SUBMITTED])->save();
    }

    // ── Client controller ───────────────────────────────────────────────────

    public function test_stranger_client_cannot_read_inspection(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->getJson("/api/client/inspections/{$this->inspection->id}")->assertForbidden();
    }

    public function test_owner_client_can_read_inspection(): void
    {
        Sanctum::actingAs($this->ownerClient);

        $this->getJson("/api/client/inspections/{$this->inspection->id}")->assertOk();
    }

    public function test_stranger_client_cannot_validate_inspection(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->postJson("/api/client/inspections/{$this->inspection->id}/validate", [
            'terms_accepted' => true,
            'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
        ])->assertForbidden();

        // The inspection must remain unvalidated (integrity preserved).
        $this->assertSame(MissionQualityInspection::STATUS_SUBMITTED, $this->inspection->fresh()->status);
    }

    public function test_stranger_client_cannot_dispute_inspection(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->postJson("/api/client/inspections/{$this->inspection->id}/dispute", [
            'reason' => 'This is a long enough dispute reason to pass validation.',
        ])->assertForbidden();
    }

    // ── Provider controller ─────────────────────────────────────────────────

    public function test_stranger_provider_cannot_read_inspection(): void
    {
        Sanctum::actingAs(User::factory()->employe()->create());

        $this->getJson("/api/provider/inspections/{$this->inspection->id}")->assertForbidden();
    }

    public function test_owner_provider_can_read_inspection(): void
    {
        Sanctum::actingAs($this->ownerProvider);

        $this->getJson("/api/provider/inspections/{$this->inspection->id}")->assertOk();
    }

    public function test_stranger_provider_cannot_submit_inspection(): void
    {
        Sanctum::actingAs(User::factory()->employe()->create());

        $this->postJson("/api/provider/inspections/{$this->inspection->id}/submit")->assertForbidden();
    }

    public function test_stranger_provider_cannot_list_mission_inspections(): void
    {
        Sanctum::actingAs(User::factory()->employe()->create());

        $this->getJson("/api/provider/missions/{$this->inspection->mission_id}/inspections")->assertForbidden();
    }

    public function test_stranger_provider_cannot_start_inspection_on_foreign_mission(): void
    {
        Sanctum::actingAs(User::factory()->employe()->create());

        $this->postJson("/api/provider/missions/{$this->inspection->mission_id}/inspections", [
            'phase' => 'post',
        ])->assertForbidden();
    }
}
