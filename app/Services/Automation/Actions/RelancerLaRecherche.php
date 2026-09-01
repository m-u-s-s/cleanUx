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
        if ($offre === null) {
            return ActionResult::echouee('Aucune offre émise : mission déjà pourvue, ou plus aucun prestataire éligible.');
        }

        // `accepted` SANS QUE PERSONNE N'AIT ACCEPTE : c'est le repli d'office du moteur. Seul
        // `assignByDefault()` l'ecrit ; toute offre, immediate ou planifiee, nait `assigned`.
        return $offre->assignment_status === 'accepted'
            ? ActionResult::reussie("Mission imposée d'office au prestataire #{$offre->user_id} : personne n'avait accepté.")
            : ActionResult::reussie("Offre #{$offre->id} émise au prestataire #{$offre->user_id}.");
    }
}
