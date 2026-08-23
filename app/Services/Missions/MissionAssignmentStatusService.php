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
        // Une affectation révoquée n'ouvre plus rien — voir `Mission::estIntervenant()`.
        $isAssigned = $mission->estIntervenant($user);

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
            ...$this->sansEcraserLAcceptation($assignment, $extra),
        ]);
    }

    /**
     * `accepted_at` S'ÉCRIT UNE FOIS. C'est une date, pas un compteur d'activité.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function sansEcraserLAcceptation(MissionAssignment $assignment, array $extra): array
    {
        if (array_key_exists('accepted_at', $extra) && $assignment->accepted_at !== null) {
            unset($extra['accepted_at']);
        }

        return $extra;
    }

    public function syncLeadAssignment(Mission $mission, ?int $leadEmployeeId): void
    {
        if (! $leadEmployeeId) {
            return;
        }

        $chef = MissionAssignment::query()->firstOrNew([
            'mission_id' => $mission->id,
            'user_id' => $leadEmployeeId,
        ]);

        $chef->role_on_mission = 'lead';
        $chef->assigned_at = $chef->assigned_at ?? now();

        // ON NE RÉTROGRADE PAS UNE OFFRE DÉJÀ ACCEPTÉE.
        if (! in_array($chef->assignment_status, ['accepted', 'completed'], true)) {
            $chef->assignment_status = 'assigned';
        }

        $chef->save();

        // LES AUTRES SONT ANNULÉES, PAS SUPPRIMÉES.
        MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->where('user_id', '!=', $leadEmployeeId)
            ->whereIn('assignment_status', ['assigned'])
            ->update([
                'assignment_status' => 'cancelled',
                'declined_at' => now(),
                'decline_reason' => 'Un autre professionnel a été retenu',
            ]);
    }
}
