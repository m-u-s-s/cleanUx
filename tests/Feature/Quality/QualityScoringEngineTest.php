<?php

namespace Tests\Feature\Quality;

use App\Models\InspectionItem;
use App\Models\MissionQualityInspection;
use App\Models\QualityChecklist;
use App\Models\QualityChecklistItem;
use App\Services\Quality\QualityScoringEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityScoringEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function makeChecklist(): QualityChecklist
    {
        $cl = QualityChecklist::create([
            'code' => 'test_post_' . uniqid(),
            'name' => 'Test',
            'phase' => QualityChecklist::PHASE_POST,
            'is_active' => true,
        ]);
        return $cl;
    }

    protected function addItem(QualityChecklist $cl, array $attrs): QualityChecklistItem
    {
        return QualityChecklistItem::create(array_merge([
            'checklist_id' => $cl->id,
            'position' => 1,
            'code' => 'item_' . uniqid(),
            'label' => 'Test item',
            'item_type' => 'boolean',
            'required' => true,
            'weight' => 1,
        ], $attrs));
    }

    public function test_boolean_item_awards_full_weight_when_true(): void
    {
        $cl = $this->makeChecklist();
        $item = $this->addItem($cl, ['item_type' => 'boolean', 'weight' => 3]);

        $insp = MissionQualityInspection::create([
            'mission_id' => 1,
            'checklist_id' => $cl->id,
            'phase' => 'post',
        ]);
        $row = InspectionItem::create([
            'inspection_id' => $insp->id,
            'checklist_item_id' => $item->id,
            'value' => ['answer' => true],
        ]);

        $result = app(QualityScoringEngine::class)->recompute($insp);

        $row->refresh();
        $this->assertSame(3, (int) $row->score_awarded);
        $this->assertTrue((bool) $row->met);
        $this->assertEqualsWithDelta(3.0, (float) $result->score_calculated, 0.01);
        $this->assertSame(3, (int) $result->score_max);
    }

    public function test_boolean_item_awards_zero_when_false(): void
    {
        $cl = $this->makeChecklist();
        $item = $this->addItem($cl, ['item_type' => 'boolean', 'weight' => 3]);

        $insp = MissionQualityInspection::create([
            'mission_id' => 1,
            'checklist_id' => $cl->id,
            'phase' => 'post',
        ]);
        $row = InspectionItem::create([
            'inspection_id' => $insp->id,
            'checklist_item_id' => $item->id,
            'value' => ['answer' => false],
        ]);

        $result = app(QualityScoringEngine::class)->recompute($insp);

        $row->refresh();
        $this->assertSame(0, (int) $row->score_awarded);
        $this->assertFalse((bool) $row->met);
        $this->assertEqualsWithDelta(0.0, (float) $result->score_calculated, 0.01);
    }

    public function test_rating_item_scales_by_max(): void
    {
        $cl = $this->makeChecklist();
        $item = $this->addItem($cl, [
            'item_type' => 'rating', 'weight' => 5,
            'valid_options' => ['max' => 5],
        ]);

        $insp = MissionQualityInspection::create([
            'mission_id' => 1,
            'checklist_id' => $cl->id,
            'phase' => 'post',
        ]);
        InspectionItem::create([
            'inspection_id' => $insp->id,
            'checklist_item_id' => $item->id,
            'value' => ['rating' => 3],
        ]);

        $result = app(QualityScoringEngine::class)->recompute($insp);

        // 3/5 * 5 = 3
        $this->assertEqualsWithDelta(3.0, (float) $result->score_calculated, 0.01);
        $this->assertSame(5, (int) $result->score_max);
    }

    public function test_photo_item_awards_when_photos_count_positive(): void
    {
        $cl = $this->makeChecklist();
        $item = $this->addItem($cl, ['item_type' => 'photo', 'weight' => 2]);

        $insp = MissionQualityInspection::create([
            'mission_id' => 1,
            'checklist_id' => $cl->id,
            'phase' => 'post',
        ]);
        InspectionItem::create([
            'inspection_id' => $insp->id,
            'checklist_item_id' => $item->id,
            'value' => [],
            'photos_count' => 2,
        ]);

        $result = app(QualityScoringEngine::class)->recompute($insp);

        $this->assertEqualsWithDelta(2.0, (float) $result->score_calculated, 0.01);
    }

    public function test_grade_excellent_pass_fail(): void
    {
        $cl = $this->makeChecklist();
        $this->addItem($cl, ['item_type' => 'boolean', 'weight' => 10]);

        $insp = MissionQualityInspection::create([
            'mission_id' => 1,
            'checklist_id' => $cl->id,
            'phase' => 'post',
            'score_calculated' => 9.5,
            'score_max' => 10,
        ]);

        $this->assertSame('excellent', app(QualityScoringEngine::class)->gradeFor($insp));

        $insp->forceFill(['score_calculated' => 7.5])->save();
        $this->assertSame('pass', app(QualityScoringEngine::class)->gradeFor($insp));

        $insp->forceFill(['score_calculated' => 5])->save();
        $this->assertSame('fail', app(QualityScoringEngine::class)->gradeFor($insp));
    }

    public function test_select_item_awards_only_for_accepted_values(): void
    {
        $cl = $this->makeChecklist();
        $item = $this->addItem($cl, [
            'item_type' => 'select', 'weight' => 4,
            'expected_value' => ['accepted' => ['ok', 'great']],
        ]);

        $insp = MissionQualityInspection::create([
            'mission_id' => 1,
            'checklist_id' => $cl->id,
            'phase' => 'post',
        ]);

        InspectionItem::create([
            'inspection_id' => $insp->id,
            'checklist_item_id' => $item->id,
            'value' => ['selected' => 'bad'],
        ]);

        $result = app(QualityScoringEngine::class)->recompute($insp);
        $this->assertEqualsWithDelta(0.0, (float) $result->score_calculated, 0.01);

        InspectionItem::query()->update(['value' => json_encode(['selected' => 'ok'])]);
        $result2 = app(QualityScoringEngine::class)->recompute($insp);
        $this->assertEqualsWithDelta(4.0, (float) $result2->score_calculated, 0.01);
    }
}
