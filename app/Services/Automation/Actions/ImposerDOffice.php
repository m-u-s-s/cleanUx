<?php

namespace App\Services\Automation\Actions;

use App\Models\Mission;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Dispatch\DispatchEngine;
use Illuminate\Database\Eloquent\Model;

/** Designe un prestataire SANS son accord. L'acte le plus tranchant du moteur : il se nomme. */
class ImposerDOffice implements Action
{
    public function __construct(protected DispatchEngine $moteur) {}

    public function cle(): string
    {
        return 'mission.imposer_doffice';
    }

    public function libelle(): string
    {
        return "Imposer la mission d'office à un prestataire";
    }

    public function entitesSupportees(): array
    {
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

        $assignation = $this->moteur->imposerDOffice($entite);

        // `null` = deja pourvue, hors du planifie, ou aucun candidat a contraindre.
        if ($assignation === null) {
            return ActionResult::echouee('Aucune imposition : mission déjà pourvue, ou aucun prestataire à désigner.');
        }

        return ActionResult::reussie("Mission imposée d'office au prestataire #{$assignation->user_id} : personne n'avait accepté.");
    }
}
