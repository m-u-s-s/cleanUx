<?php

namespace App\Services\Missions;

use App\Models\EnterpriseWorkOrder;
use App\Models\Mission;
use App\Models\MissionBatch;
use App\Models\MissionBatchDay;
use App\Models\MissionTaskSegment;
use App\Models\WorkOrderLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EnterpriseWorkOrderMissionGeneratorService
{
    public function __construct(
        protected OperationalLoadCalculator $loadCalculator,
    ) {
    }

    public function ensureBatchForWorkOrder(EnterpriseWorkOrder $workOrder): MissionBatch
    {
        $startsAt = Carbon::parse($workOrder->scheduled_start_at ?? $workOrder->requested_start_at ?? now());
        $endsAt = Carbon::parse($workOrder->scheduled_end_at ?? $workOrder->requested_end_at ?? $startsAt->copy()->addDay());

        $batch = $workOrder->generatedBatch ?: MissionBatch::query()->firstOrCreate(
            ['reference' => 'WO-' . $workOrder->reference],
            [
                'organization_account_id' => $workOrder->organization_account_id,
                'organization_site_id' => $workOrder->organization_site_id,
                'enterprise_work_order_id' => $workOrder->id,
                'field_team_id' => $workOrder->assigned_field_team_id,
                'service_partner_id' => $workOrder->assigned_service_partner_id,
                'name' => 'Batch ' . $workOrder->title,
                'status' => 'planned',
                'batch_type' => 'work_order_generated',
                'starts_on' => $startsAt->toDateString(),
                'ends_on' => $endsAt->toDateString(),
                'default_start_time' => $startsAt->format('H:i:s'),
                'default_end_time' => $endsAt->format('H:i:s'),
                'estimated_total_minutes' => 0,
                'estimated_total_cost' => (float) ($workOrder->budget_amount ?? 0),
                'auto_generate_missions' => true,
                'generation_status' => 'pending',
                'metadata' => [
                    'source' => 'enterprise_work_order',
                    'work_type' => $workOrder->work_type,
                ],
                'notes' => $workOrder->instructions,
            ]
        );

        $workOrder->forceFill([
            'generated_batch_id' => $batch->id,
            'generation_status' => 'batch_created',
            'generation_started_at' => $workOrder->generation_started_at ?? now(),
        ])->save();

        $this->seedBatchDaysAndSegments($batch, $workOrder, $startsAt, $endsAt);

        if (! $batch->field_team_id) {
            $batch->field_team_id = optional($this->loadCalculator->recommendFieldTeamForBatch($batch, $startsAt))->id;
        }

        if (! $batch->service_partner_id) {
            $batch->service_partner_id = optional($this->loadCalculator->recommendPartnerForBatch($batch, $startsAt))->id;
        }

        $batch->save();

        return $batch->fresh(['days.segments', 'segments']);
    }

    protected function seedBatchDaysAndSegments(MissionBatch $batch, EnterpriseWorkOrder $workOrder, Carbon $startsAt, Carbon $endsAt): void
    {
        if ($batch->segments()->exists()) {
            return;
        }

        $lines = $workOrder->lines()->get();
        if ($lines->isEmpty()) {
            $lines = collect([$this->buildSyntheticLine($workOrder)]);
        }

        $daysSpan = max(1, $startsAt->copy()->startOfDay()->diffInDays($endsAt->copy()->startOfDay()) + 1);
        $totalMinutes = 0;

        for ($dayIndex = 0; $dayIndex < $daysSpan; $dayIndex++) {
            $serviceDate = $startsAt->copy()->startOfDay()->addDays($dayIndex);
            $day = MissionBatchDay::query()->create([
                'mission_batch_id' => $batch->id,
                'field_team_id' => $batch->field_team_id,
                'service_partner_id' => $batch->service_partner_id,
                'status' => 'planned',
                'service_date' => $serviceDate->toDateString(),
                'planned_start_at' => $serviceDate->copy()->setTimeFromTimeString($batch->default_start_time ?? '08:00:00'),
                'planned_end_at' => $serviceDate->copy()->setTimeFromTimeString($batch->default_end_time ?? '17:00:00'),
                'target_mission_count' => $lines->count(),
                'metadata' => ['source_work_order_id' => $workOrder->id],
                'notes' => $workOrder->instructions,
            ]);

            foreach ($lines as $sequence => $line) {
                $estimatedMinutes = (int) data_get($line->metadata, 'estimated_minutes', max(60, ((float) $line->quantity) * 60));
                $totalMinutes += $estimatedMinutes;

                MissionTaskSegment::query()->create([
                    'mission_batch_id' => $batch->id,
                    'mission_batch_day_id' => $day->id,
                    'field_team_id' => $batch->field_team_id,
                    'service_partner_id' => $batch->service_partner_id,
                    'assigned_user_id' => null,
                    'service_catalog_id' => $line->service_catalog_id ?: $workOrder->service_catalog_id,
                    'service_zone_id' => $workOrder->service_zone_id,
                    'status' => 'planned',
                    'segment_type' => 'work_order_line',
                    'title' => $line->title ?: ('Segment ' . ($sequence + 1)),
                    'zone_label' => optional($workOrder->organizationSite)->name ?? optional($workOrder->organizationAccount)->name,
                    'service_date' => $serviceDate->toDateString(),
                    'planned_start_at' => $serviceDate->copy()->setTimeFromTimeString($batch->default_start_time ?? '08:00:00')->addMinutes($sequence * 90),
                    'planned_end_at' => $serviceDate->copy()->setTimeFromTimeString($batch->default_start_time ?? '08:00:00')->addMinutes(($sequence * 90) + $estimatedMinutes),
                    'estimated_minutes' => $estimatedMinutes,
                    'crew_size' => (int) data_get($line->metadata, 'crew_size', 1),
                    'sequence' => $sequence + 1,
                    'auto_generate_mission' => true,
                    'generation_status' => 'pending',
                    'metadata' => [
                        'source_work_order_line_id' => $line->id,
                        'unit' => $line->unit,
                    ],
                    'notes' => $line->description,
                ]);
            }
        }

        $batch->forceFill([
            'estimated_total_minutes' => $totalMinutes,
            'total_segment_minutes' => $totalMinutes,
            'generation_status' => 'segments_planned',
        ])->save();
    }

    protected function buildSyntheticLine(EnterpriseWorkOrder $workOrder): WorkOrderLine
    {
        return new WorkOrderLine([
            'service_catalog_id' => $workOrder->service_catalog_id,
            'title' => $workOrder->title,
            'description' => $workOrder->instructions,
            'quantity' => 1,
            'unit' => 'forfait',
            'unit_price' => $workOrder->budget_amount,
            'line_total' => $workOrder->budget_amount,
            'metadata' => [
                'estimated_minutes' => 180,
                'crew_size' => 1,
            ],
        ]);
    }

    public function materializePendingMissionsForBatch(MissionBatch $batch): Collection
    {
        $created = collect();

        $segments = $batch->segments()
            ->where('auto_generate_mission', true)
            ->whereNull('mission_id')
            ->orderBy('service_date')
            ->orderBy('sequence')
            ->get();

        foreach ($segments as $segment) {
            $mission = Mission::query()->create([
                'rendez_vous_id' => null,
                'enterprise_work_order_id' => $batch->enterprise_work_order_id,
                'mission_batch_id' => $batch->id,
                'mission_task_segment_id' => $segment->id,
                'organization_account_id' => $batch->organization_account_id,
                'organization_site_id' => $batch->organization_site_id,
                'service_catalog_id' => $segment->service_catalog_id,
                'service_zone_id' => $segment->service_zone_id,
                'lead_employee_id' => $segment->assigned_user_id,
                'status' => $segment->assigned_user_id || $segment->field_team_id || $segment->service_partner_id ? 'assigned' : 'planned',
                'mission_type' => 'batched_execution',
                'planned_start_at' => $segment->planned_start_at,
                'planned_end_at' => $segment->planned_end_at,
                'requires_start_code' => true,
                'requires_end_code' => true,
                'client_presence_confirmed' => false,
                'notes' => $segment->notes ?: ('Auto-generated from batch ' . $batch->reference),
            ]);

            $segment->forceFill([
                'mission_id' => $mission->id,
                'generation_status' => 'generated',
            ])->save();

            $created->push($mission);
        }

        $generatedCount = $batch->segments()->whereNotNull('mission_id')->count();

        $batch->forceFill([
            'generated_missions_count' => $generatedCount,
            'generation_status' => $generatedCount > 0 ? 'generated' : 'pending',
        ])->save();

        if ($batch->enterpriseWorkOrder) {
            $batch->enterpriseWorkOrder->forceFill([
                'generated_missions_count' => $generatedCount,
                'generation_status' => $generatedCount > 0 ? 'generated' : 'pending',
                'generation_completed_at' => $generatedCount > 0 ? now() : null,
            ])->save();
        }

        return $created;
    }

    public function runForApprovedWorkOrder(EnterpriseWorkOrder $workOrder): array
    {
        if (! $workOrder->isApproved()) {
            return [
                'batch' => null,
                'missions_created' => 0,
                'status' => 'skipped_not_approved',
            ];
        }

        $batch = $this->ensureBatchForWorkOrder($workOrder);
        $missions = $this->materializePendingMissionsForBatch($batch);

        return [
            'batch' => $batch,
            'missions_created' => $missions->count(),
            'status' => 'generated',
        ];
    }

    public function generateForApprovedPendingWorkOrders(\DateTimeInterface|string|null $forDate = null): Collection
    {
        $date = Carbon::parse($forDate ?? now());

        return EnterpriseWorkOrder::query()
            ->approvedForGeneration()
            ->where(function ($query) use ($date) {
                $query->whereNull('scheduled_start_at')
                    ->orWhereDate('scheduled_start_at', '<=', $date->toDateString());
            })
            ->get()
            ->map(fn (EnterpriseWorkOrder $workOrder) => $this->runForApprovedWorkOrder($workOrder));
    }
}
