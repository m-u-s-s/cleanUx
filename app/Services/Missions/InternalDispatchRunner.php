<?php

namespace App\Services\Missions;

use App\Models\InternalAssignmentDecision;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Services\Organizations\OrganizationNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * EXÉCUTER UNE DÉCISION DU MOTEUR — choisir, assigner, tracer.
 *
 * Le moteur ne fait que classer ; il n'écrit rien, pour qu'on puisse l'interroger à blanc. Ce
 * service est ce qui transforme un classement en travail distribué, et c'est lui qui porte les
 * garde-fous d'une action prise SANS que personne ne regarde :
 *
 *   - le verrou par mission, parce que deux exécutions concurrentes assigneraient deux fois ;
 *   - la revérification après verrou, parce qu'un humain a pu prendre la mission entre-temps ;
 *   - la trace, systématique, y compris quand rien n'a pu être fait.
 *
 * L'ALERTE « AUCUN CANDIDAT » PART IMMÉDIATEMENT, pas dans un résumé de fin de traitement. Une
 * mission de demain matin que personne ne peut prendre est une urgence : la découvrir en fin de
 * lot, ou dans un récapitulatif qu'on lira le lendemain, revient à la découvrir trop tard.
 */
class InternalDispatchRunner
{
    public function __construct(
        private InternalAutoAssignmentEngine $moteur,
        private MissionAssignmentService $assignations,
        private OrganizationNotifier $notifier,
    ) {}

    /**
     * Traiter une mission. Rend la décision enregistrée.
     */
    public function traiter(Mission $mission, string $mode, ?int $declencheurId = null): InternalAssignmentDecision
    {
        $organisationId = (int) $mission->provider_organization_id;

        /*
         * VERROU PUIS REVÉRIFICATION, dans cet ordre.
         *
         * `lockForUpdate()` est un no-op sous SQLite — la suite de tests ne peut donc pas prouver le
         * verrou lui-même. Ce qu'elle prouve, et qui compte autant, c'est que la LOGIQUE de
         * revérification existe : une mission déjà pourvue entre le moment où on l'a listée et celui
         * où on la traite est laissée tranquille, plutôt que réassignée par-dessus un choix humain.
         */
        $decision = DB::transaction(function () use ($mission, $mode, $declencheurId, $organisationId) {
            $fraiche = Mission::query()->lockForUpdate()->find($mission->id);

            if ($fraiche === null || $fraiche->lead_provider_user_id !== null) {
                return $this->tracer($mission, $organisationId, $mode, $declencheurId, [
                    'status' => InternalAssignmentDecision::STATUS_SKIPPED_LOCKED,
                ]);
            }

            $choix = $this->moteur->choisirPour($fraiche);

            if ($choix['chosen_user_id'] === null) {
                return $this->tracer($mission, $organisationId, $mode, $declencheurId, [
                    'status' => InternalAssignmentDecision::STATUS_NO_CANDIDATE,
                    'candidates' => $choix['candidates'],
                ]);
            }

            $membre = OrganizationMember::query()
                ->where('organization_account_id', $organisationId)
                ->where('user_id', $choix['chosen_user_id'])
                ->where('status', 'active')
                ->first();

            if ($membre === null) {
                return $this->tracer($mission, $organisationId, $mode, $declencheurId, [
                    'status' => InternalAssignmentDecision::STATUS_NO_CANDIDATE,
                    'candidates' => $choix['candidates'],
                ]);
            }

            $this->assignations->assigner($fraiche, $membre, $declencheurId);

            return $this->tracer($mission, $organisationId, $mode, $declencheurId, [
                'status' => InternalAssignmentDecision::STATUS_ASSIGNED,
                'chosen_user_id' => $choix['chosen_user_id'],
                'chosen_score' => $choix['chosen_score'],
                'candidates' => $choix['candidates'],
            ]);
        });

        if ($decision->status === InternalAssignmentDecision::STATUS_NO_CANDIDATE) {
            $this->alerterSurLAbsenceDeCandidat($mission, $organisationId, $declencheurId);
        }

        return $decision;
    }

    /**
     * Les missions d'une société qui attendent quelqu'un.
     *
     * À VENIR SEULEMENT. Assigner rétroactivement une mission d'hier ne sert personne et fausserait
     * la charge du jour de celui qu'on désignerait.
     *
     * @return Collection<int, Mission>
     */
    public function arriere(int $organisationId)
    {
        return Mission::query()
            ->where('provider_organization_id', $organisationId)
            ->whereNull('lead_provider_user_id')
            ->whereNotNull('planned_start_at')
            ->where('planned_start_at', '>=', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('planned_start_at')
            ->limit((int) config('internal_dispatch.lot_maximum', 200))
            ->get();
    }

    /**
     * L'ALERTE IMMÉDIATE — décision produit du 2026-08-08.
     *
     * Un résumé de fin de job aurait suffi à la machine ; pas au gérant. Une mission de demain matin
     * que personne ne peut prendre demande un arbitrage humain tout de suite : appeler un
     * intérimaire, décaler le client, y aller soi-même. Le savoir le lendemain, c'est le savoir trop
     * tard.
     */
    private function alerterSurLAbsenceDeCandidat(Mission $mission, int $organisationId, ?int $declencheurId): void
    {
        $quand = $mission->planned_start_at?->format('d/m à H:i') ?? 'à une date à préciser';

        $this->notifier->notifierPorteursDe(
            organisationId: $organisationId,
            permission: 'missions.dispatch',
            titre: 'Mission sans personne',
            corps: "Aucun collaborateur disponible pour la mission du {$quand}.",
            donnees: ['type' => 'mission_no_candidate', 'mission_id' => $mission->id],
            // Le déclencheur n'a pas besoin d'être notifié : il regarde l'écran qui le lui dit déjà.
            saufUtilisateurId: $declencheurId,
            cleIdempotence: "mission:{$mission->id}:no_candidate",
        );
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function tracer(
        Mission $mission,
        int $organisationId,
        string $mode,
        ?int $declencheurId,
        array $attributs,
    ): InternalAssignmentDecision {
        return InternalAssignmentDecision::create(array_merge([
            'mission_id' => $mission->id,
            'provider_organization_id' => $organisationId,
            'triggered_by' => $declencheurId,
            'mode' => $mode,
        ], $attributs));
    }
}
