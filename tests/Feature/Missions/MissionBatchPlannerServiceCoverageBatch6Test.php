<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\MissionTaskSegment;
use App\Services\Missions\MissionBatchPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionBatchPlannerServiceCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    private function service(): MissionBatchPlannerService
    {
        return app(MissionBatchPlannerService::class);
    }

    public function test_create_batch_spans_multiple_days_with_segments_and_generated_reference(): void
    {
        $batch = $this->service()->createBatch([
            'name' => 'Tour de nettoyage',
            'starts_on' => '2026-06-10',
            'ends_on' => '2026-06-12',
            'segments_per_day' => 2,
            'target_mission_count_per_day' => 3,
            'estimated_segment_minutes' => 90,
            'crew_size' => 2,
            'zone_label' => 'Aile Nord',
            'estimated_total_minutes' => 540,
            'metadata' => ['source' => 'test'],
            'notes' => 'Note de lot',
        ]);

        $this->assertStringStartsWith('BATCH-', $batch->reference);
        $this->assertSame('planned', $batch->status);
        $this->assertSame('multi_day_site', $batch->batch_type);

        // 3 calendar days -> 3 days, each with 2 segments -> 6 segments.
        $this->assertCount(3, $batch->days);
        $this->assertSame(6, $batch->segments()->count());

        $firstDay = $batch->days->first();
        $this->assertSame(3, $firstDay->target_mission_count);
        $this->assertSame(2, $firstDay->segments()->count());

        $segment = $firstDay->segments()->orderBy('sequence')->first();
        $this->assertSame(90, $segment->estimated_minutes);
        $this->assertSame(2, $segment->crew_size);
        $this->assertSame('Aile Nord', $segment->zone_label);
        $this->assertSame('execution_zone', $segment->segment_type);
    }

    public function test_create_batch_single_day_uses_starts_on_when_ends_on_missing(): void
    {
        $batch = $this->service()->createBatch([
            'name' => 'Mono-jour',
            'reference' => 'BATCH-FIXED-1',
            'starts_on' => '2026-07-01',
            'segments_per_day' => 0, // forced to min 1 by the service
        ]);

        $this->assertSame('BATCH-FIXED-1', $batch->reference);
        $this->assertCount(1, $batch->days);
        // max(1, 0) guard keeps at least one segment.
        $this->assertSame(1, $batch->segments()->count());
    }

    public function test_attach_mission_to_segment_marks_scheduled_and_advances_batch(): void
    {
        $batch = $this->service()->createBatch([
            'name' => 'Attach',
            'starts_on' => '2026-06-10',
        ]);
        $segment = $batch->segments()->first();

        $mission = Mission::factory()->create();

        $result = $this->service()->attachMissionToSegment($mission, $segment);

        $this->assertSame($mission->id, $result->id);

        $segment->refresh();
        $this->assertSame($mission->id, $segment->mission_id);
        $this->assertSame('scheduled', $segment->status);

        // Recalculated batch status: an active (scheduled) segment -> in_progress.
        $this->assertSame('in_progress', $batch->fresh()->status);
    }

    public function test_rebalance_counters_raises_target_to_planned_plus_done(): void
    {
        $batch = $this->service()->createBatch([
            'name' => 'Rebalance',
            'starts_on' => '2026-06-10',
            'segments_per_day' => 3,
            'target_mission_count_per_day' => 1,
        ]);

        $day = $batch->days->first();
        $segments = $day->segments()->orderBy('sequence')->get();
        $segments[0]->update(['status' => 'completed']);
        $segments[1]->update(['status' => 'scheduled']);
        // segments[2] stays planned -> planned + done = 3

        $fresh = $this->service()->rebalanceCounters($batch);

        $this->assertSame(3, $fresh->days->first()->target_mission_count);
        // One scheduled segment exists -> batch is in_progress.
        $this->assertSame('in_progress', $fresh->status);
    }

    public function test_recalculate_status_completed_when_all_segments_done(): void
    {
        $batch = $this->service()->createBatch([
            'name' => 'AllDone',
            'starts_on' => '2026-06-10',
            'segments_per_day' => 2,
        ]);

        MissionTaskSegment::query()
            ->where('mission_batch_id', $batch->id)
            ->update(['status' => 'completed']);

        $this->service()->recalculateBatchStatus($batch);

        $this->assertSame('completed', $batch->fresh()->status);
    }

    public function test_recalculate_status_draft_when_no_segments(): void
    {
        $batch = MissionBatch::create([
            'name' => 'Empty',
            'reference' => 'BATCH-EMPTY-1',
            'status' => 'planned',
            'batch_type' => 'multi_day_site',
            'starts_on' => '2026-06-10',
            'ends_on' => '2026-06-10',
            'estimated_total_minutes' => 0,
            'estimated_total_cost' => 0,
            'metadata' => [],
        ]);

        $this->service()->recalculateBatchStatus($batch);

        $this->assertSame('draft', $batch->fresh()->status);
    }
}
