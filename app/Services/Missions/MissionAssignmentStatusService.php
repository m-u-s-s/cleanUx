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
     * Chaque transition — en route, arrivé, client à bord — passe `accepted_at => now()`, avec une
     * intention défendable : « en faisant cela, le prestataire a forcément accepté ». Mais appliqué
     * sans garde, cela réécrivait la date à chaque geste. Une ligne finissait par affirmer que le
     * chauffeur avait accepté APRÈS être arrivé, ce qui n'est pas seulement faux, c'est impossible.
     *
     * Ce que ça coûte : le délai de réponse d'un prestataire, son taux d'acceptation, et toute
     * reconstitution après litige — « à quelle heure a-t-il pris la course ? » — reposent sur cette
     * colonne. Aucune exception n'était levée, aucun test ne regardait : la donnée se dégradait en
     * silence à chaque mission.
     *
     * On garde donc l'intention (stamper si personne ne l'a jamais fait) et on retire l'écrasement.
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
