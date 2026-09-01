<?php

namespace App\Services\Automation\Actions;

use App\Models\Mission;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Dispatch\DispatchEngine;
use Illuminate\Database\Eloquent\Model;

/** Remet une mission dans le moteur de repartition. Elle CHERCHE : elle n'impose jamais. */
class RelancerLaRecherche implements Action
{
    public function __construct(protected DispatchEngine $moteur) {}

    public function cle(): string
    {
        return 'mission.relancer_la_recherche';
    }

    public function libelle(): string
    {
        return "Relancer la recherche d'un prestataire";
    }

    public function entitesSupportees(): array
    {
        // La mission SEULE : `next()` est la porte du moteur, et elle prend une mission.
        return ['mission'];
    }

    public function champs(): array
    {
        return [];
    }

    public function toucheAuDomaine(): bool
    {
        return true;
    }

    public function executer(Model $entite, array $parametres): ActionResult
    {
        if (! $entite instanceof Mission) {
            return ActionResult::echouee('Cette action ne vise que les missions.');
        }

        // LA PORTE D'OFFICE RESTE FERMEE. « Relancer la recherche » et « imposer d'office » sont
        // deux actes ; celui-ci ne fait que le premier, valide ou autonome.
        $offre = $this->moteur->next($entite, imposerSiEpuise: false);

        // `null` = deja pourvue, offre en cours, ou plus personne d'eligible. Aucune offre, echec.
        if ($offre === null) {
            return ActionResult::echouee('Aucune offre émise : mission déjà pourvue, ou plus aucun prestataire éligible.');
        }

        return ActionResult::reussie("Offre #{$offre->id} émise au prestataire #{$offre->user_id}.");
    }
}
