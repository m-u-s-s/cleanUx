<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\ProviderSiteAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * QUI ENVOYER SUR CETTE MISSION — entre les salariés d'UNE MÊME société.
 *
 * RIEN À VOIR AVEC `MatchingScoreEngine`, et la confusion coûterait cher. Celui-là choisit un
 * PRESTATAIRE sur la place de marché : réputation, prix, distance, taux d'acceptation. Ici la
 * société est déjà choisie, et la question est de savoir lequel de ses employés y va. On ne classe
 * pas ses propres salariés sur leur note client.
 *
 * LA DISPONIBILITÉ EST UN FILTRE ÉLIMINATOIRE, PAS UN SCORE. Quelqu'un déjà pris à cette heure-là
 * sort du classement au lieu d'y descendre : le pondérer laisserait un très bon score compenser une
 * impossibilité physique, et enverrait la même personne à deux endroits en même temps.
 *
 * LE MOTEUR N'ÉCRIT RIEN. Il rend une décision — le choisi, les candidats, le détail des points —
 * et l'appelant assigne et trace. Un moteur qui assignerait lui-même serait impossible à
 * interroger « à blanc », et c'est précisément ce qu'un gérant veut faire avant d'appuyer.
 */
class InternalAutoAssignmentEngine
{
    public function __construct(private WorkerAvailabilityService $disponibilites) {}

    /**
     * Départager les candidats pour cette mission.
     *
     * @param  ?list<int>  $bassin  restreindre à ces personnes ; tous les membres actifs si `null`
     * @return array{
     *     chosen_user_id: int|null,
     *     chosen_score: int|null,
     *     candidates: list<array{user_id: int, score: int, detail: array<string, int>}>
     * }
     */
    public function choisirPour(Mission $mission, ?array $bassin = null): array
    {
        $vide = ['chosen_user_id' => null, 'chosen_score' => null, 'candidates' => []];

        $organisationId = $mission->provider_organization_id;
        $debut = $mission->planned_start_at;

        /*
         * SANS HORAIRE, ON NE DÉCIDE PAS.
         *
         * Toute la notion de disponibilité repose sur un créneau. Choisir « au hasard parmi les
         * membres » serait pire que ne rien faire : la mission paraîtrait couverte, et le conflit
         * n'apparaîtrait que le jour même.
         */
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

            // Déclarée et inactive jusqu'au lot 6 : voir `config/internal_dispatch.php`.
            if ((int) $poids['agence'] !== 0) {
                $detail['agence'] = (int) $poids['agence'];
            }

            $candidats[] = [
                'user_id' => $userId,
                'score' => array_sum($detail),
                'detail' => $detail,
            ];
        }

        /*
         * Tri par score décroissant, puis par identifiant CROISSANT.
         *
         * Le second critère n'est pas cosmétique : sans lui, deux candidats à égalité seraient
         * départagés par l'ordre de la base, qui varie. Une décision automatique doit être
         * REJOUABLE — c'est ce qui permet de répondre « voici pourquoi » un mois plus tard.
         */
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

    /**
     * Les points de rotation : un par jour depuis la dernière mission, plafonné.
     *
     * Jamais assigné = le plafond. C'est délibéré : un nouvel arrivant, ou quelqu'un qu'on a oublié,
     * doit passer devant celui qui sort d'une mission hier. Rendre 0 le laisserait au fond du
     * classement précisément parce qu'on ne lui a rien donné.
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
     * Scopé sur `provider_organization_id` : plusieurs sociétés concurrentes peuvent desservir le
     * même immeuble, et lire les référents de l'autre serait à la fois faux et une fuite.
     *
     * @return array<int, string> user_id => role
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
     * L'ABSENCE DE MÉTIER DÉCLARÉ NE PÉNALISE PERSONNE : on n'accorde pas le bonus, on n'élimine
     * pas. `trade_user` est peu rempli sur les déploiements existants — en faire un filtre viderait
     * le bassin de candidats et transformerait une préférence en panne.
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
