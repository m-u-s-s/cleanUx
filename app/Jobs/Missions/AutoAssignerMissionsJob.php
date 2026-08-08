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

/**
 * « ASSIGNER TOUTES LES MISSIONS NON ATTRIBUÉES » — le bouton du gérant.
 *
 * EN FILE, ET PAS DANS LA REQUÊTE. Un arriéré de deux cents missions, c'est deux cents décisions et
 * autant de notifications : le faire pendant que le navigateur attend donnerait un écran figé, puis
 * un timeout qui laisserait le travail à moitié fait, sans que rien ne dise où il s'est arrêté.
 *
 * `ShouldBeUnique` PAR SOCIÉTÉ. Un double-clic sur le bouton — le geste le plus naturel quand rien
 * ne semble se passer — lancerait deux passages concurrents sur le même arriéré. Ils choisiraient
 * les mêmes personnes pour les mêmes missions, et le second écraserait le premier. Le verrou est
 * porté par l'identifiant d'organisation : deux sociétés différentes ne s'attendent pas.
 */
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

    /**
     * Combien de temps le verrou tient si le processus meurt sans le relâcher.
     *
     * Sans cette borne, un worker tué laisserait la société incapable de relancer son
     * auto-assignation — et la seule issue serait une intervention en base.
     */
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

        /*
         * LE RÉSUMÉ NE REMPLACE PAS L'ALERTE, IL LA COMPLÈTE.
         *
         * Chaque mission sans candidat a DÉJÀ déclenché sa propre notification — décision produit du
         * 2026-08-08 : une mission de demain matin que personne ne peut prendre est une urgence, la
         * découvrir en fin de traitement serait la découvrir trop tard. Ce résumé donne le compte
         * d'ensemble, qu'aucune alerte unitaire ne porte.
         */
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
