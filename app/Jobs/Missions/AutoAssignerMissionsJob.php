<?php

namespace App\Jobs\Missions;

use App\Models\InternalAssignmentDecision;
use App\Services\Missions\InternalDispatchRunner;
use App\Services\Organizations\OrganizationNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** « ASSIGNER TOUTES LES MISSIONS NON ATTRIBUÉES » — le bouton du gérant. */
class AutoAssignerMissionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $organisationId,
        public ?int $declencheurId = null,
    ) {}

    /** Le verrou porte sur la SOCIÉTÉ : deux sociétés distinctes n'ont pas à s'attendre. */
    public function uniqueId(): string
    {
        return (string) $this->organisationId;
    }

    /** Combien de temps le verrou tient si le processus meurt sans le relâcher. */
    public function uniqueFor(): int
    {
        return (int) config('internal_dispatch.verrou_job_secondes', 300);
    }

    public function handle(InternalDispatchRunner $runner, OrganizationNotifier $notifier): void
    {
        $missions = $runner->arriere($this->organisationId);

        if ($missions->isEmpty()) {
            return;
        }

        $assignees = 0;
        $sansCandidat = 0;

        foreach ($missions as $mission) {
            $decision = $runner->traiter(
                $mission,
                InternalAssignmentDecision::MODE_AUTO_BUTTON,
                $this->declencheurId,
            );

            match ($decision->status) {
                InternalAssignmentDecision::STATUS_ASSIGNED => $assignees++,
                InternalAssignmentDecision::STATUS_NO_CANDIDATE => $sansCandidat++,
                default => null,
            };
        }

        // LE RÉSUMÉ NE REMPLACE PAS L'ALERTE, IL LA COMPLÈTE.
        $notifier->notifierPorteursDe(
            organisationId: $this->organisationId,
            permission: 'missions.dispatch',
            titre: 'Répartition automatique terminée',
            corps: $sansCandidat > 0
                ? "{$assignees} mission(s) assignée(s), {$sansCandidat} sans personne disponible."
                : "{$assignees} mission(s) assignée(s).",
            donnees: ['type' => 'auto_assign_summary', 'assigned' => $assignees, 'no_candidate' => $sansCandidat],
        );
    }
}
