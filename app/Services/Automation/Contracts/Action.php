<?php

namespace App\Services\Automation\Contracts;

use App\Services\Automation\ActionResult;
use Illuminate\Database\Eloquent\Model;

/** Ce qu'une action doit savoir dire d'elle-meme, et savoir faire. */
interface Action
{
    public function cle(): string;

    public function libelle(): string;

    /** @return list<string> les cles d'entite que cette action accepte */
    public function entitesSupportees(): array;

    /** @return array<string, string> nom du parametre => type, pour construire le formulaire */
    public function champs(): array;

    /** Ecrit-elle dans le domaine metier ? Ne decide PAS de l'autonomie — voir la spec. */
    public function toucheAuDomaine(): bool;

    /** @param array<string, mixed> $parametres */
    public function executer(Model $entite, array $parametres): ActionResult;
}
