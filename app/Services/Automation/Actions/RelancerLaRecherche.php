<?php

namespace App\Services\Automation\Actions;

use App\Models\Mission;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Dispatch\DispatchEngine;
use Illuminate\Database\Eloquent\Model;

/** Remet une mission dans le moteur de repartition. Qui est eligible reste l'affaire du moteur. */
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

        $offre = $this->moteur->next($entite);

        // `null` = deja pourvue, offre en cours, ou plus personne d'eligible. Aucune offre, echec.
        return $offre === null
            ? ActionResult::echouee('Aucune offre émise : mission déjà pourvue, ou plus aucun prestataire éligible.')
            : ActionResult::reussie("Offre #{$offre->id} émise.");
    }
}
