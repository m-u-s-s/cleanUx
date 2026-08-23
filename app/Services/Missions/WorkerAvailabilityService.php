<?php

namespace App\Services\Missions;

use App\Models\LeaveRequest;
use App\Models\MissionAssignment;
use App\Models\OrganizationMember;
use App\Models\Shift;
use Illuminate\Support\Carbon;

/** QUI EST DÉJÀ PRIS SUR CE CRÉNEAU — la disponibilité d'un SALARIÉ. */
class WorkerAvailabilityService
{
    /**
     * Les membres actifs de l'organisation, avec pour chacun s'il est libre sur ce créneau.
     *
     * @param  ?list<int>  $userIds  restreindre à ces personnes ; toute l'organisation si `null`
     * @param  ?int  $exclureMissionId  la mission qu'on s'apprête à confier — se réassigner à
     *                                  soi-même n'est pas un conflit
     * @return array<int, bool> user_id => libre
     *                          /
     */
    public function libresPour(
        int $organisationId,
        Carbon $debut,
        ?Carbon $fin = null,
        ?array $userIds = null,
        ?int $exclureMissionId = null,
    ): array {
        // Sans fin prévue, deux heures : la question est « déjà pris à ce moment-là », pas
        // « combien de temps exactement ».
        $fin ??= $debut->copy()->addHours(2);

        $identifiants = $userIds ?? $this->membresActifs($organisationId);

        if ($identifiants === []) {
            return [];
        }

        $occupes = $this->occupesSur($identifiants, $debut, $fin, $exclureMissionId);

        // DISPONIBLE = EN SHIFT **ET** SANS CHEVAUCHEMENT (E19).
        $planifies = $this->planifiesSur($organisationId, $identifiants, $debut, $fin);

        // UN CONGÉ APPROUVÉ BLOQUE (E21), et c'est tout l'intérêt de la fonctionnalité.
        $enConge = $this->enCongeLe($organisationId, $identifiants, $debut);

        $verdicts = [];

        foreach ($identifiants as $id) {
            $libre = ! in_array($id, $occupes, true) && ! in_array($id, $enConge, true);

            if ($planifies !== null) {
                $libre = $libre && in_array($id, $planifies, true);
            }

            $verdicts[$id] = $libre;
        }

        return $verdicts;
    }

    /**
     * Qui est PLANIFIÉ sur ce créneau, ou `null` si cette société n'a pas de planning ce jour-là.
     *
     * @param  list<int>  $userIds
     * @return list<int>|null
     */
    protected function planifiesSur(int $organisationId, array $userIds, Carbon $debut, Carbon $fin): ?array
    {
        // LA QUESTION SE POSE À L'ÉCHELLE DE LA JOURNÉE, PAS DU CRÉNEAU.
        $journeeDebut = $debut->copy()->startOfDay();
        $journeeFin = $debut->copy()->endOfDay();

        $planningDuJour = Shift::query()
            ->where('organization_account_id', $organisationId)
            ->where('status', Shift::STATUS_PUBLISHED)
            ->where('starts_at', '<', $journeeFin)
            ->where('ends_at', '>', $journeeDebut)
            ->exists();

        if (! $planningDuJour) {
            return null;
        }

        return Shift::query()
            ->where('organization_account_id', $organisationId)
            ->where('status', Shift::STATUS_PUBLISHED)
            // Chevauchement de créneaux : le shift commence avant la fin demandée et finit après le
            // début demandé. Comparer sur une seule borne laisserait passer un shift qui englobe.
            ->where('starts_at', '<', $fin)
            ->where('ends_at', '>', $debut)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->intersect($userIds)
            ->values()
            ->all();
    }

    /**
     * Qui est en congé APPROUVÉ ce jour-là.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    protected function enCongeLe(int $organisationId, array $userIds, Carbon $moment): array
    {
        $jour = $moment->copy()->startOfDay()->toDateString();

        return LeaveRequest::query()
            ->where('organization_account_id', $organisationId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereIn('user_id', $userIds)
            // Bornes inclusives : un congé du 3 au 7 couvre le 7. L'exclure ferait travailler
            // quelqu'un le dernier jour de ses vacances.
            ->whereDate('starts_on', '<=', $jour)
            ->whereDate('ends_on', '>=', $jour)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Combien de missions cette personne a déjà ce jour-là.
     *
     * @param  list<int>  $userIds
     * @return array<int, int> user_id => nombre
     *                         /
     */
    public function chargeDuJour(array $userIds, Carbon $jour): array
    {
        if ($userIds === []) {
            return [];
        }

        $comptes = MissionAssignment::query()
            ->whereIn('mission_assignments.user_id', $userIds)
            ->where('mission_assignments.assignment_status', 'assigned')
            ->join('missions', 'missions.id', '=', 'mission_assignments.mission_id')
            ->whereNotNull('missions.planned_start_at')
            // Comparaison sur les BORNES DE LA JOURNÉE, pas `whereDate()`.
            ->whereBetween('missions.planned_start_at', [$jour->copy()->startOfDay(), $jour->copy()->endOfDay()])
            ->selectRaw('mission_assignments.user_id as uid, count(*) as total')
            ->groupBy('mission_assignments.user_id')
            ->pluck('total', 'uid');

        $charges = [];

        foreach ($userIds as $id) {
            $charges[$id] = (int) ($comptes[$id] ?? 0);
        }

        return $charges;
    }

    /**
     * Quand chacun a travaillé pour la dernière fois, avant ce moment.
     *
     * @param  list<int>  $userIds
     * @return array<int, ?Carbon> user_id => date, ou `null` si jamais assigné
     *                             /
     */
    public function dernieresMissions(array $userIds, Carbon $avant): array
    {
        if ($userIds === []) {
            return [];
        }

        $dates = MissionAssignment::query()
            ->whereIn('mission_assignments.user_id', $userIds)
            ->join('missions', 'missions.id', '=', 'mission_assignments.mission_id')
            ->whereNotNull('missions.planned_start_at')
            ->where('missions.planned_start_at', '<', $avant)
            ->selectRaw('mission_assignments.user_id as uid, max(missions.planned_start_at) as derniere')
            ->groupBy('mission_assignments.user_id')
            ->pluck('derniere', 'uid');

        $resultat = [];

        foreach ($userIds as $id) {
            $valeur = $dates[$id] ?? null;
            $resultat[$id] = $valeur !== null ? Carbon::parse($valeur) : null;
        }

        return $resultat;
    }

    /** @return list<int> */
    private function membresActifs(int $organisationId): array
    {
        return OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('status', 'active')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Chevauchement classique : (début_autre < fin_demandée) ET (fin_autre > début_demandée).
     *
     * @param  list<int>  $identifiants
     * @return list<int>
     */
    private function occupesSur(array $identifiants, Carbon $debut, Carbon $fin, ?int $exclureMissionId): array
    {
        return MissionAssignment::query()
            ->whereIn('mission_assignments.user_id', $identifiants)
            ->where('mission_assignments.assignment_status', 'assigned')
            ->when(
                $exclureMissionId !== null,
                fn ($q) => $q->where('mission_assignments.mission_id', '!=', $exclureMissionId)
            )
            ->join('missions', 'missions.id', '=', 'mission_assignments.mission_id')
            ->whereNotNull('missions.planned_start_at')
            ->where('missions.planned_start_at', '<', $fin)
            ->where(fn ($q) => $q
                ->where('missions.planned_end_at', '>', $debut)
                ->orWhereNull('missions.planned_end_at')
            )
            ->distinct()
            ->pluck('mission_assignments.user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
