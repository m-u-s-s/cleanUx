<?php

namespace Tests\Feature\Quality;

use App\Models\Booking;
use App\Models\ClientSignature;
use App\Models\MissionQualityInspection;
use App\Models\QualityChecklist;
use App\Models\QualityChecklistItem;
use App\Models\User;
use App\Services\Quality\QualityInspectionService;
use Database\Seeders\QualityChecklistsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QualityInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(QualityChecklistsSeeder::class);
        Config::set('quality.enabled', true);
    }

    public function test_start_creates_inspection_in_progress(): void
    {
        $provider = User::factory()->employe()->create();
        $insp = app(QualityInspectionService::class)->start(
            missionId: 42, phase: 'post', provider: $provider,
        );

        $this->assertInstanceOf(MissionQualityInspection::class, $insp);
        $this->assertSame(MissionQualityInspection::STATUS_IN_PROGRESS, $insp->status);
        $this->assertSame(42, (int) $insp->mission_id);
        $this->assertNotNull($insp->checklist_id);
    }

    public function test_start_is_idempotent_with_same_mission_phase(): void
    {
        $provider = User::factory()->employe()->create();
        $svc = app(QualityInspectionService::class);

        $a = $svc->start(42, 'post', $provider);
        $b = $svc->start(42, 'post', $provider);

        $this->assertSame($a->id, $b->id);
    }

    public function test_start_rejects_invalid_phase(): void
    {
        $this->expectException(ValidationException::class);
        app(QualityInspectionService::class)->start(1, 'midflight');
    }

    public function test_submit_item_creates_response(): void
    {
        $provider = User::factory()->employe()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);
        $item = $insp->checklist->items->first();

        $row = app(QualityInspectionService::class)->submitItem(
            $insp, $item, ['answer' => true], 'tout ok', $provider,
        );

        $this->assertSame($item->id, (int) $row->checklist_item_id);
        $this->assertSame(['answer' => true], $row->value);
    }

    public function test_submit_item_updates_existing_response(): void
    {
        $provider = User::factory()->employe()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);
        $item = $insp->checklist->items->first();

        $svc = app(QualityInspectionService::class);
        $svc->submitItem($insp, $item, ['answer' => false]);
        $svc->submitItem($insp, $item, ['answer' => true]);

        $this->assertSame(1, $insp->items()->count());
        $row = $insp->items()->first();
        $this->assertSame(['answer' => true], $row->value);
    }

    public function test_submit_finalizes_inspection_with_score(): void
    {
        $provider = User::factory()->employe()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);

        foreach ($insp->checklist->items as $item) {
            $value = match ($item->item_type) {
                'boolean' => ['answer' => true],
                'rating' => ['rating' => 5],
                'photo' => [],
                default => ['text' => 'ok'],
            };
            $rec = app(QualityInspectionService::class)->submitItem($insp, $item, $value);
            if ($item->item_type === 'photo') {
                $rec->forceFill(['photos_count' => 1])->save();
            }
        }

        $result = app(QualityInspectionService::class)->submit($insp, $provider);

        $this->assertSame(MissionQualityInspection::STATUS_SUBMITTED, $result->status);
        $this->assertNotNull($result->score_calculated);
        $this->assertGreaterThan(0, (float) $result->score_calculated);
    }

    public function test_submit_rejects_different_provider(): void
    {
        $provider = User::factory()->employe()->create();
        $stranger = User::factory()->employe()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);

        $this->expectException(ValidationException::class);
        app(QualityInspectionService::class)->submit($insp, $stranger);
    }

    public function test_validate_by_client_requires_signature_by_default(): void
    {
        Config::set('quality.signature_required_for_client_validation', true);

        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);

        $insp->forceFill(['status' => MissionQualityInspection::STATUS_SUBMITTED])->save();

        $this->expectException(ValidationException::class);
        app(QualityInspectionService::class)->validateByClient($insp, $client);
    }

    public function test_validate_by_client_persists_signature(): void
    {
        Config::set('quality.signature_required_for_client_validation', true);

        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);
        $insp->forceFill(['status' => MissionQualityInspection::STATUS_SUBMITTED])->save();

        $result = app(QualityInspectionService::class)->validateByClient(
            $insp, $client, 'data:image/png;base64,iVBORw0KGgo=', 'John Doe',
        );

        $this->assertSame(MissionQualityInspection::STATUS_VALIDATED_CLIENT, $result->status);
        $this->assertSame(1, ClientSignature::count());
        $sig = ClientSignature::first();
        $this->assertSame('John Doe', $sig->signer_name);
        $this->assertNotNull($sig->signer_email_hash);
    }

    public function test_dispute_requires_min_reason_length(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);
        $insp->forceFill(['status' => MissionQualityInspection::STATUS_SUBMITTED])->save();

        $this->expectException(ValidationException::class);
        app(QualityInspectionService::class)->dispute($insp, $client, 'too short');
    }

    public function test_dispute_changes_status(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);
        $insp->forceFill(['status' => MissionQualityInspection::STATUS_SUBMITTED])->save();

        $result = app(QualityInspectionService::class)->dispute(
            $insp, $client, 'Le sol était encore sale après la prestation, photos à l\'appui.',
        );

        $this->assertSame(MissionQualityInspection::STATUS_DISPUTED, $result->status);
        $this->assertNotNull($result->dispute_reason);
        $this->assertNotNull($result->disputed_at);
    }

    public function test_admin_validate_resolves_dispute(): void
    {
        $provider = User::factory()->employe()->create();
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $insp = app(QualityInspectionService::class)->start(1, 'post', $provider);
        $insp->forceFill(['status' => MissionQualityInspection::STATUS_DISPUTED])->save();

        $result = app(QualityInspectionService::class)->validateByAdmin($insp, $admin);

        $this->assertSame(MissionQualityInspection::STATUS_VALIDATED_ADMIN, $result->status);
        $this->assertSame($admin->id, (int) $result->validated_by_user_id);
    }
}
