<?php

namespace App\Services\Cerveau;

use App\Models\User;
use Closure;

/**
 * UN GESTE QUE LE CERVEAU PEUT PROPOSER — jamais appliquer seul.
 *
 * Chaque geste porte trois choses, et les trois s'affichent AVANT le clic :
 *   — CE QU'IL FAIT, en une phrase que quelqu'un qui n'a pas écrit le code comprend ;
 *   — CE QU'IL IMPLIQUE, y compris les effets qu'on ne voit pas tout de suite ;
 *   — S'IL EST RÉVERSIBLE, et comment revenir en arrière quand il ne l'est pas.
 *
 * SORTIR DE L'ARGENT N'EST PAS UN GESTE. Aucun remboursement, aucun virement, aucune capture ne
 * figure dans ce registre : une automatisation qui déplace de l'argent finit toujours par le
 * déplacer une fois de trop.
 */
final readonly class Geste
{
    public function __construct(
        public string $cle,
        public string $domaine,
        public string $libelle,
        /** Ce qu'il fait, en clair. */
        public string $fait,
        /** Ce qu'il implique — y compris ce qui ne se voit pas tout de suite. */
        public string $implique,
        public bool $reversible,
        /** @var Closure(User, array<string, mixed>): string Rend le compte rendu de ce qui a été fait. */
        public Closure $executer,
    ) {}
}
