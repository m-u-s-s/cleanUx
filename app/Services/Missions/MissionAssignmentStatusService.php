<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use RuntimeException;

class MissionAssignmentStatusService
{
    public function assertAssignedToMission(Mission $mission, User $user): void
    {
        $isAssigned = $mission->lead_employee_id === $user->id
            || $mission->assignments()->where('user_id', $user->id)->exists();

        if (! $isAssigned) {
            throw new RuntimeException('Utilisateur non affecté à cette mission.');
        }
    }

    public function updateAssignmentStatus(Mission $mission, User $user, string $status, array $extra = []): void
    {
        $assignment = $mission->assignments()->where('user_id', $user->id)->first();

        if (! $assignment) {
            MissionAssignment::query()->create([
                'mission_id' => $mission->id,
                'user_id' => $user->id,
                'role_on_mission' => 'member',
                'assignment_status' => $status,
                'assigned_at' => now(),
                ...$extra,
            ]);

            return;
        }

        $assignment->update([
            'assignment_status' => $status,
            ...$extra,
        ]);
    }

    public function syncLeadAssignment(Mission $mission, ?int $leadEmployeeId): void
    {
        if (! $leadEmployeeId) {
            return;
        }

        MissionAssignment::query()->updateOrCreate(
            [
                'mission_id' => $mission->id,
                'user_id' => $leadEmployeeId,
            ],
            [
                'role_on_mission' => 'lead',
                'assignment_status' => 'assigned',
                'assigned_at' => now(),
            ]
        );

        MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', '!=', $leadEmployeeId)
            ->delete();
    }
}
