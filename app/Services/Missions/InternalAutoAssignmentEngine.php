<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\ProviderSiteAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** QUI ENVOYER SUR CETTE MISSION — entre les salariés d'UNE MÊME société. */
class InternalAutoAssignmentEngine
{
    public function __construct(private WorkerAvailabilityService $disponibilites) {}

    /**
     * Départager les candidats pour cette mission.
     *
     * @param  ?list<int>  $bassin  restreindre à ces personnes ; tous les membres actifs si `null`
     * @return array{
     * chosen_user_id: int|null,
     * chosen_score: int|null,
     * candidates: list<array{user_id: int, score: int, detail: array<string, int>}>
     * }
     */
    public function choisirPour(Mission $mission, ?array $bassin = null): array
    {
        $vide = ['chosen_user_id' => null, 'chosen_score' => null, 'candidates' => []];

        $organisationId = $mission->provider_organization_id;
        $debut = $mission->planned_start_at;

        // SANS HORAIRE, ON NE DÉCIDE PAS. Toute la notion de disponibilité repose sur un créneau.
        if ($organisationId === null || $debut === null) {
            return $vide;
        }

        $fin = $mission->planned_end_at
            ?? $debut->copy()->addHours((int) config('internal_dispatch.duree_par_defaut_heures', 2));

        $verdicts = $this->disponibilites->libresPour(
            organisationId: (int) $organisationId,
            debut: $debut,
            fin: $fin,
            userIds: $bassin,
            exclureMissionId: $mission->id,
        );

        $libres = array_keys(array_filter($verdicts));

        if ($libres === []) {
            return $vide;
        }

        $poids = config('internal_dispatch.poids');

        $referents = $this->referentsDuSite($mission);
        $charges = $this->disponibilites->chargeDuJour($libres, $debut);
        $dernieres = $this->disponibilites->dernieresMissions($libres, $debut);
        $duMetier = $this->duMetierDeLaMission($mission, $libres);
        $agenceDeLaMission = $this->agenceDeLaMission($mission);
        $agenceDesMembres = $this->agencesDesMembres((int) $organisationId, $libres);

        $candidats = [];

        foreach ($libres as $userId) {
            $detail = [];

            $role = $referents[$userId] ?? null;

            if ($role === ProviderSiteAssignment::ROLE_LEAD) {
                $detail['referent_site'] = (int) $poids['referent_site_lead'];
            } elseif ($role === ProviderSiteAssignment::ROLE_BACKUP) {
                $detail['referent_site'] = (int) $poids['referent_site_backup'];
            }

            $charge = $charges[$userId] ?? 0;

            if ($charge > 0) {
                $detail['charge'] = $charge * (int) $poids['charge_par_mission'];
            }

            $detail['rotation'] = $this->pointsDeRotation($dernieres[$userId] ?? null, $debut, $poids);

            if (in_array($userId, $duMetier, true)) {
                $detail['metier'] = (int) $poids['metier'];
            }

            // L'AGENCE — activée au lot 6.
            if ($agenceDeLaMission !== null
                && ($agenceDesMembres[$userId] ?? null) === $agenceDeLaMission) {
                $detail['agence'] = (int) $poids['agence'];
            }

            $candidats[] = [
                'user_id' => $userId,
                'score' => array_sum($detail),
                'detail' => $detail,
            ];
        }

        // Tri par score décroissant, puis par identifiant CROISSANT.
        usort(
            $candidats,
            fn (array $a, array $b) => $b['score'] <=> $a['score'] ?: $a['user_id'] <=> $b['user_id'],
        );

        return [
            'chosen_user_id' => $candidats[0]['user_id'],
            'chosen_score' => $candidats[0]['score'],
            'candidates' => $candidats,
        ];
    }

    /** De quelle implantation relève cette mission. */
    private function agenceDeLaMission(Mission $mission): ?int
    {
        if ($mission->provider_agency_id !== null) {
            return (int) $mission->provider_agency_id;
        }

        if ($mission->field_team_id === null) {
            return null;
        }

        $agence = DB::table('field_teams')
            ->where('id', $mission->field_team_id)
            ->value('provider_agency_id');

        return $agence !== null ? (int) $agence : null;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, ?int> user_id => agence
     *                          /
     */
    private function agencesDesMembres(int $organisationId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('organization_members')
            ->where('organization_account_id', $organisationId)
            ->whereIn('user_id', $userIds)
            ->pluck('provider_agency_id', 'user_id')
            ->map(fn ($id) => $id !== null ? (int) $id : null)
            ->all();
    }

    /**
     * Les points de rotation : un par jour depuis la dernière mission, plafonné.
     *
     * @param  array<string, int>  $poids
     */
    private function pointsDeRotation(?Carbon $derniere, Carbon $reference, array $poids): int
    {
        $plafond = (int) $poids['rotation_plafond'];

        if ($derniere === null) {
            return $plafond;
        }

        $jours = (int) $derniere->diffInDays($reference);

        return min($jours * (int) $poids['rotation_par_jour'], $plafond);
    }

    /**
     * Les référents que NOTRE société place sur le site de cette mission.
     *
     * @return array<int, string> user_id => role
     *                            /
     */
    private function referentsDuSite(Mission $mission): array
    {
        if ($mission->organization_site_id === null) {
            return [];
        }

        return ProviderSiteAssignment::query()
            ->where('provider_organization_id', $mission->provider_organization_id)
            ->where('organization_site_id', $mission->organization_site_id)
            ->pluck('role', 'user_id')
            ->map(fn ($role) => (string) $role)
            ->all();
    }

    /**
     * Ceux qui portent le métier de la mission.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function duMetierDeLaMission(Mission $mission, array $userIds): array
    {
        if ($mission->service_catalog_id === null || $userIds === []) {
            return [];
        }

        $tradeId = DB::table('service_catalogs')
            ->where('id', $mission->service_catalog_id)
            ->value('trade_id');

        if ($tradeId === null) {
            return [];
        }

        return DB::table('trade_user')
            ->whereIn('user_id', $userIds)
            ->where('trade_id', $tradeId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
