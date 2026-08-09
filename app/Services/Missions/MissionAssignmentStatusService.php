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

        $chef = MissionAssignment::query()->firstOrNew([
            'mission_id' => $mission->id,
            'user_id' => $leadEmployeeId,
        ]);

        $chef->role_on_mission = 'lead';
        $chef->assigned_at = $chef->assigned_at ?? now();

        /*
         * ON NE RÉTROGRADE PAS UNE OFFRE DÉJÀ ACCEPTÉE.
         *
         * Ce service est appelé chaque fois qu'une réservation est sauvegardée. Écrire `assigned`
         * sans condition ramenait l'offre que le prestataire venait d'accepter à l'état « en
         * attente de réponse » — et le compte à rebours d'expiration repartait sur une mission
         * déjà prise.
         */
        if (! in_array($chef->assignment_status, ['accepted', 'completed'], true)) {
            $chef->assignment_status = 'assigned';
        }

        $chef->save();

        /*
         * LES AUTRES SONT ANNULÉES, PAS SUPPRIMÉES.
         *
         * Elles l'étaient — `->delete()` — et cela effaçait l'histoire de la recherche : qui a été
         * sollicité, qui a refusé, qui n'a pas répondu. C'est précisément la matière du taux
         * d'acceptation, du centre de dispatch et de toute explication a posteriori. Le taux
         * d'acceptation d'un prestataire n'existe que parce que chaque événement est une ligne.
         */
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
