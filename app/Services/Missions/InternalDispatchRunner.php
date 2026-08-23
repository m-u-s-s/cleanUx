<?php

namespace App\Services\Missions;

use App\Models\InternalAssignmentDecision;
use App\Models\Mission;
use App\Models\OrganizationMember;
use App\Services\Organizations\OrganizationNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** EXÉCUTER UNE DÉCISION DU MOTEUR — choisir, assigner, tracer. */
class InternalDispatchRunner
{
    public function __construct(
        private InternalAutoAssignmentEngine $moteur,
        private MissionAssignmentService $assignations,
        private OrganizationNotifier $notifier,
    ) {}

    /** Traiter une mission. Rend la décision enregistrée. */
    public function traiter(Mission $mission, string $mode, ?int $declencheurId = null): InternalAssignmentDecision
    {
        $organisationId = (int) $mission->provider_organization_id;

        // VERROU PUIS REVÉRIFICATION, dans cet ordre.
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
     * Les missions d'une société qui attendent quelqu'un. À VENIR SEULEMENT.
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

    /** L'ALERTE IMMÉDIATE — décision produit du 2026-08-08. */
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
