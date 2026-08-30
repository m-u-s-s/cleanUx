<?php

namespace App\Services\Automation\Actions;

use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/** Ecrit une ligne au journal d'activite. N'ecrit rien dans le domaine. */
class Journaliser implements Action
{
    public function cle(): string
    {
        return 'journaliser';
    }

    public function libelle(): string
    {
        return "Écrire au journal d'activité";
    }

    public function entitesSupportees(): array
    {
        // Generique : ecrire au journal ne touche a aucun champ propre a une entite.
        return ['booking', 'alerte', 'mission'];
    }

    public function champs(): array
    {
        return ['message' => 'texte'];
    }

    public function toucheAuDomaine(): bool
    {
        return false;
    }

    public function executer(Model $entite, array $parametres): ActionResult
    {
        ActivityLogger::log('automation.note', $entite, [
            'message' => (string) ($parametres['message'] ?? ''),
        ]);

        return ActionResult::reussie();
    }
}
