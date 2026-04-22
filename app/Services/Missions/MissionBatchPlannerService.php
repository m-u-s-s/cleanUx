<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\MissionBatchDay;
use App\Models\MissionTaskSegment;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MissionBatchPlannerService
{
    public function createBatch(array $payload): MissionBatch
    {
        $startsOn = Carbon::parse($payload['starts_on'])->startOfDay();
        $endsOn = Carbon::parse($payload['ends_on'] ?? $payload['starts_on'])->startOfDay();

        $batch = MissionBatch::create([
            'organization_account_id' => $payload['organization_account_id'] ?? null,
            'organization_site_id' => $payload['organization_site_id'] ?? null,
            'enterprise_work_order_id' => $payload['enterprise_work_order_id'] ?? null,
            'field_team_id' => $payload['field_team_id'] ?? null,
            'service_partner_id' => $payload['service_partner_id'] ?? null,
            'name' => $payload['name'],
            'reference' => $payload['reference'] ?? $this->generateReference(),
            'status' => $payload['status'] ?? 'planned',
            'batch_type' => $payload['batch_type'] ?? 'multi_day_site',
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'estimated_total_minutes' => (int) ($payload['estimated_total_minutes'] ?? 0),
            'estimated_total_cost' => $payload['estimated_total_cost'] ?? 0,
            'metadata' => (array) ($payload['metadata'] ?? []),
            'notes' => $payload['notes'] ?? null,
        ]);

        $dayIndex = 1;
        for ($date = $startsOn->copy(); $date->lte($endsOn); $date->addDay(), $dayIndex++) {
            $day = MissionBatchDay::create([
                'mission_batch_id' => $batch->id,
                'field_team_id' => $payload['field_team_id'] ?? null,
                'service_partner_id' => $payload['service_partner_id'] ?? null,
                'status' => 'planned',
                'service_date' => $date->copy(),
                'target_mission_count' => (int) ($payload['target_mission_count_per_day'] ?? 1),
                'metadata' => ['day_index' => $dayIndex],
                'notes' => $payload['day_notes'] ?? null,
            ]);

            $segmentCount = max(1, (int) ($payload['segments_per_day'] ?? 1));
            for ($sequence = 1; $sequence <= $segmentCount; $sequence++) {
                MissionTaskSegment::create([
                    'mission_batch_id' => $batch->id,
                    'mission_batch_day_id' => $day->id,
                    'field_team_id' => $payload['field_team_id'] ?? null,
                    'service_partner_id' => $payload['service_partner_id'] ?? null,
                    'status' => 'planned',
                    'segment_type' => $payload['segment_type'] ?? 'execution_zone',
                    'title' => ($payload['name'] ?? 'Lot') . ' · Jour ' . $dayIndex . ' · Segment ' . $sequence,
                    'zone_label' => Arr::get($payload, 'zone_label'),
                    'service_date' => $date->copy(),
                    'estimated_minutes' => (int) ($payload['estimated_segment_minutes'] ?? 180),
                    'crew_size' => (int) ($payload['crew_size'] ?? 1),
                    'sequence' => $sequence,
                    'metadata' => (array) ($payload['segment_metadata'] ?? []),
                    'notes' => $payload['segment_notes'] ?? null,
                ]);
            }
        }

        return $batch->load(['days.segments']);
    }

    public function attachMissionToSegment(Mission $mission, MissionTaskSegment $segment): Mission
    {
        $segment->update([
            'mission_id' => $mission->id,
            'assigned_user_id' => $segment->assigned_user_id ?: $mission->lead_employee_id,
            'status' => 'scheduled',
        ]);

        $this->recalculateBatchStatus($segment->batch);

        return $mission->refresh();
    }

    public function rebalanceCounters(MissionBatch $batch): MissionBatch
    {
        foreach ($batch->days as $day) {
            $planned = $day->segments()->whereIn('status', ['planned', 'scheduled', 'in_progress'])->count();
            $done = $day->segments()->where('status', 'completed')->count();

            $day->update([
                'target_mission_count' => max($day->target_mission_count ?? 0, $planned + $done),
            ]);
        }

        $this->recalculateBatchStatus($batch);

        return $batch->fresh(['days.segments']);
    }

    public function recalculateBatchStatus(MissionBatch $batch): void
    {
        $stats = $batch->segments()
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN status IN ('in_progress', 'scheduled') THEN 1 ELSE 0 END) as active_count")
            ->selectRaw('COUNT(*) as total_count')
            ->first();

        $status = 'planned';

        if (($stats->completed_count ?? 0) > 0 && ($stats->completed_count ?? 0) === ($stats->total_count ?? 0)) {
            $status = 'completed';
        } elseif (($stats->active_count ?? 0) > 0) {
            $status = 'in_progress';
        } elseif (($stats->total_count ?? 0) === 0) {
            $status = 'draft';
        }

        $batch->update(['status' => $status]);
    }

    protected function generateReference(): string
    {
        return 'BATCH-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
    }
}
