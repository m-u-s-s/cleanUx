<?php

namespace App\Services\Missions;

use App\Models\MissionAssignment;
use App\Models\OrganizationMember;
use App\Models\Shift;
use Illuminate\Support\Carbon;

/**
 * QUI EST DÉJÀ PRIS SUR CE CRÉNEAU — la disponibilité d'un SALARIÉ.
 *
 * POURQUOI PAS `AvailabilityService::isAvailable()`. Mesuré : il rend `false` pour un employé sans
 * créneaux déclarés, et coûte ~200 ms par personne. Les créneaux sont un concept de prestataire
 * INDÉPENDANT — celui qui publie ses disponibilités sur la place de marché. Un salarié de société ne
 * s'en déclare aucun : c'est son employeur qui le planifie. L'employer ici afficherait
 * « indisponible » sur toute l'équipe, en permanence, et ferait attendre l'écran plusieurs secondes
 * pour cela.
 *
 * La question qu'un répartiteur pose réellement est « cette personne est-elle déjà prise à cette
 * heure-là », et la réponse vit dans SES PROPRES missions. UNE SEULE REQUÊTE pour toute l'équipe,
 * jamais une par personne.
 *
 * INDICATIF POUR UN HUMAIN, ÉLIMINATOIRE POUR LA MACHINE. Un répartiteur qui connaît son équipe
 * passe outre pour de bonnes raisons — un échange entre collègues, une heure supplémentaire
 * consentie. Le moteur d'auto-assignation, lui, n'a pas ce discernement : il élimine, sinon il
 * enverrait quelqu'un à deux endroits en même temps sans que personne ne s'en aperçoive.
 *
 * Cette logique vivait dans `DispatchCenter::getDisponibilitesProperty()`, l'écran web. Le moteur et
 * l'API en ont besoin à leur tour ; la recopier aurait produit deux définitions de « libre ».
 */
class WorkerAvailabilityService
{
    /**
     * Les membres actifs de l'organisation, avec pour chacun s'il est libre sur ce créneau.
     *
     * @param  ?list<int>  $userIds  restreindre à ces personnes ; toute l'organisation si `null`
     * @param  ?int  $exclureMissionId  la mission qu'on s'apprête à confier — se réassigner à
     *                                  soi-même n'est pas un conflit
     * @return array<int, bool> user_id => libre
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

        /*
         * DISPONIBLE = EN SHIFT **ET** SANS CHEVAUCHEMENT (E19).
         *
         * Cette méthode ne savait répondre qu'à la seconde moitié de la question : « cette personne
         * est-elle déjà prise ». Faute de planning, quelqu'un qui ne travaille pas ce jour-là
         * passait pour disponible — et l'auto-assignation lui envoyait une course à vingt-trois
         * heures un dimanche.
         *
         * LE PLANNING NE S'IMPOSE QUE S'IL EXISTE. Une société qui n'a pas encore saisi ses shifts
         * verrait sinon toute son équipe devenir indisponible du jour au lendemain : la migration
         * créerait la panne qu'elle devait éviter. Tant qu'aucun shift n'est publié pour la journée
         * concernée, on s'en tient au comportement d'avant.
         */
        $planifies = $this->planifiesSur($organisationId, $identifiants, $debut, $fin);

        $verdicts = [];

        foreach ($identifiants as $id) {
            $libre = ! in_array($id, $occupes, true);

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
     * Le `null` est significatif et se distingue du tableau vide : « aucun planning saisi » n'est pas
     * « personne ne travaille ». Confondre les deux rendrait toute une équipe indisponible le jour
     * où l'on branche cette table.
     *
     * Un shift `planned` ne compte pas : un planning en préparation ne doit pas rendre quelqu'un
     * assignable avant qu'il soit arrêté et communiqué.
     *
     * @param  list<int>  $userIds
     * @return list<int>|null
     */
    protected function planifiesSur(int $organisationId, array $userIds, Carbon $debut, Carbon $fin): ?array
    {
        /*
         * LA QUESTION SE POSE À L'ÉCHELLE DE LA JOURNÉE, PAS DU CRÉNEAU.
         *
         * Première version de ce garde : on cherchait les shifts chevauchant la fenêtre demandée, et
         * l'absence de résultat valait « pas de planning ». À vingt-trois heures, aucun shift ne
         * chevauche — et le garde concluait donc qu'il n'y avait pas de planning, rendant l'équipe
         * disponible en pleine nuit. Exactement le défaut qu'E19 devait corriger.
         *
         * Ce qui distingue les deux cas, c'est l'existence d'un planning POUR CE JOUR-LÀ : s'il y en
         * a un, il fait autorité, y compris pour dire que personne ne travaille à cette heure.
         */
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
     * Combien de missions cette personne a déjà ce jour-là.
     *
     * Sert au score de CHARGE du moteur : à disponibilité égale, on préfère celui qui a le moins
     * couru. Compté sur les assignments actifs, tous rôles confondus — un renfort travaille autant
     * qu'un responsable.
     *
     * @param  list<int>  $userIds
     * @return array<int, int> user_id => nombre
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
            /*
             * Comparaison sur les BORNES DE LA JOURNÉE, pas `whereDate()`.
             *
             * `whereDate` sur une colonne datetime empêche l'usage de l'index et se comporte
             * différemment selon le moteur — un piège déjà payé dans ce dépôt sur les créneaux de
             * disponibilité en SQLite.
             */
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
     * Sert au score de ROTATION : celui qu'on n'a pas fait tourner depuis longtemps passe devant, à
     * égalité par ailleurs. Sans cela, le moteur choisirait toujours le même — le mieux placé le
     * reste, et l'équité disparaîtrait derrière un score stable.
     *
     * @param  list<int>  $userIds
     * @return array<int, ?Carbon> user_id => date, ou `null` si jamais assigné
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
     * `orWhereNull(planned_end_at)` n'est pas de la prudence : une mission sans fin déclarée occupe
     * la même fenêtre par défaut, et l'omettre rendrait libre quelqu'un qui ne l'est pas.
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
