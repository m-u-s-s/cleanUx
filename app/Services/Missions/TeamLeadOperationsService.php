<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionMemberStatus;
use App\Models\MissionReinforcementRequest;
use App\Models\MissionTaskSegment;
use App\Models\MissionTaskSegmentAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeamLeadOperationsService
{
    public function assignSegment(MissionTaskSegment $segment, User $user, array $attributes = []): MissionTaskSegmentAssignment
    {
        return DB::transaction(function () use ($segment, $user, $attributes) {
            $assignment = MissionTaskSegmentAssignment::updateOrCreate(
                [
                    'mission_task_segment_id' => $segment->id,
                    'user_id' => $user->id,
                ],
                [
                    'mission_id' => $segment->mission_id,
                    'field_team_id' => $attributes['field_team_id'] ?? $segment->field_team_id,
                    'assigned_by_user_id' => $attributes['assigned_by_user_id'] ?? null,
                    'assignment_role' => $attributes['assignment_role'] ?? 'operator',
                    'status' => $attributes['status'] ?? 'assigned',
                    'planned_minutes' => $attributes['planned_minutes'] ?? $segment->estimated_minutes,
                    'sequence_order' => $attributes['sequence_order'] ?? 1,
                    'notes' => $attributes['notes'] ?? null,
                ]
            );

            MissionMemberStatus::updateOrCreate(
                [
                    'segment_assignment_id' => $assignment->id,
                    'user_id' => $user->id,
                ],
                [
                    'mission_id' => $assignment->mission_id,
                    'mission_task_segment_id' => $segment->id,
                    'field_team_id' => $assignment->field_team_id,
                    'status' => 'assigned',
                    'readiness_status' => 'pending',
                    'progress_percent' => 0,
                    'minutes_spent' => 0,
                    'is_blocked' => false,
                ]
            );

            $this->syncCollectiveProgress($assignment->mission);

            return $assignment->fresh(['memberStatuses', 'user']);
        });
    }

    public function updateMemberStatus(MissionTaskSegmentAssignment $assignment, User $user, array $payload): MissionMemberStatus
    {
        return DB::transaction(function () use ($assignment, $user, $payload) {
            $memberStatus = MissionMemberStatus::updateOrCreate(
                [
                    'segment_assignment_id' => $assignment->id,
                    'user_id' => $user->id,
                ],
                [
                    'mission_id' => $assignment->mission_id,
                    'mission_task_segment_id' => $assignment->mission_task_segment_id,
                    'field_team_id' => $assignment->field_team_id,
                    'status' => $payload['status'] ?? 'in_progress',
                    'readiness_status' => $payload['readiness_status'] ?? 'ready',
                    'progress_percent' => max(0, min(100, (int) ($payload['progress_percent'] ?? 0))),
                    'minutes_spent' => max(0, (int) ($payload['minutes_spent'] ?? 0)),
                    'is_blocked' => (bool) ($payload['is_blocked'] ?? false),
                    'blocking_reason' => $payload['blocking_reason'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'last_reported_at' => now(),
                ]
            );

            $assignment->status = $memberStatus->progress_percent >= 100 ? 'completed' : ($memberStatus->is_blocked ? 'blocked' : 'in_progress');
            if ($assignment->status === 'in_progress' && ! $assignment->started_at) {
                $assignment->started_at = now();
            }
            if ($assignment->status === 'completed' && ! $assignment->completed_at) {
                $assignment->completed_at = now();
            }
            $assignment->actual_minutes = max((int) $assignment->actual_minutes, (int) $memberStatus->minutes_spent);
            $assignment->save();

            $this->syncCollectiveProgress($assignment->mission);

            return $memberStatus->fresh();
        });
    }

    public function syncCollectiveProgress(Mission $mission): array
    {
        $segments = $mission->taskSegments()->with(['assignments.memberStatuses'])->get();
        $segmentCount = max($segments->count(), 1);

        $completedSegments = 0;
        $blockedSegments = 0;
        $weightedProgress = 0;

        foreach ($segments as $segment) {
            $statuses = $segment->assignments->flatMap->memberStatuses;
            $segmentProgress = $statuses->isEmpty() ? 0 : (int) round($statuses->avg('progress_percent'));
            $weightedProgress += $segmentProgress;

            if ($segmentProgress >= 100) {
                $completedSegments++;
            }

            if ($statuses->contains(fn ($status) => $status->is_blocked)) {
                $blockedSegments++;
            }
        }

        return [
            'progress_percent' => (int) round($weightedProgress / $segmentCount),
            'completed_segments' => $completedSegments,
            'blocked_segments' => $blockedSegments,
            'segment_count' => $segments->count(),
        ];
    }

    public function requestReinforcement(MissionTaskSegment $segment, User $requester, array $payload): MissionReinforcementRequest
    {
        return MissionReinforcementRequest::create([
            'mission_id' => $segment->mission_id,
            'mission_batch_id' => $segment->missionBatchDay?->mission_batch_id,
            'mission_batch_day_id' => $segment->mission_batch_day_id,
            'mission_task_segment_id' => $segment->id,
            'requested_by_user_id' => $requester->id,
            'field_team_id' => $payload['field_team_id'] ?? $segment->field_team_id,
            'service_partner_id' => $payload['service_partner_id'] ?? null,
            'status' => 'open',
            'priority' => $payload['priority'] ?? 'haute',
            'requested_members' => max(1, (int) ($payload['requested_members'] ?? 1)),
            'requested_minutes' => max(15, (int) ($payload['requested_minutes'] ?? 60)),
            'reason' => (string) ($payload['reason'] ?? 'Renfort demandé par le chef d’équipe.'),
        ]);
    }

    public function closeInterventionGlobally(Mission $mission, User $closedBy): array
    {
        $progress = $this->syncCollectiveProgress($mission);
        $mission->closed_by_user_id = $closedBy->id;

        if ($progress['completed_segments'] >= max(1, $progress['segment_count'])) {
            $mission->status = 'completed';
            if (! $mission->actual_end_at) {
                $mission->actual_end_at = now();
            }
        }

        $mission->save();

        return $progress + ['mission_status' => $mission->status];
    }
}
