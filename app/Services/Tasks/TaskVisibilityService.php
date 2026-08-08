<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Builder;

/**
 * QUELLES TÂCHES CETTE PERSONNE A-T-ELLE LE DROIT DE VOIR ?
 *
 * Le tableau des tâches exposait TOUTES les tâches de la société à quiconque pouvait l'ouvrir —
 * `TaskBoard::queryTasks()` et `CompanyController::tasks()` ne filtraient que sur l'organisation.
 * Un nettoyeur y lisait donc les consignes internes, les rappels de relance client et les tâches
 * nominatives de ses collègues.
 *
 * LA CLÉ QUI DÉCIDE EST `tasks.assign`, et ce n'est pas arbitraire : distribuer le travail suppose
 * de voir le tableau entier, participer au travail ne le suppose pas. Sans elle, on garde ce qui
 * nous concerne réellement — ce qu'on a écrit et ce qu'on nous a confié. Rendre une liste vide
 * serait plus simple et plus faux : ses propres tâches regardent chacun.
 *
 * LE FILTRE EST DANS LA REQUÊTE, jamais après chargement : une tâche qu'on n'a pas le droit de voir
 * n'est pas lue, donc pas comptée dans un total qui la trahirait.
 *
 * UN SEUL SERVICE POUR LES DEUX SURFACES. Le web et l'API mobile montrent le même tableau ; deux
 * filtres écrits séparément auraient dérivé au premier ajustement, et la divergence se serait vue
 * du côté le plus permissif.
 */
class TaskVisibilityService
{
    public function __construct(private PermissionService $permissions) {}

    /**
     * La requête des tâches visibles par cet utilisateur dans cette organisation.
     *
     * @return Builder<Task>
     */
    public function requetePour(User $utilisateur, ?int $organisationId): Builder
    {
        // `forOrg(null)` rend une requête impossible à satisfaire : sans organisation active, il n'y
        // a pas de tableau, et surtout pas celui de quelqu'un d'autre.
        $requete = Task::forOrg($organisationId);

        if ($organisationId === null
            || $this->permissions->can($utilisateur, 'tasks.assign', $organisationId)) {
            return $requete;
        }

        return $requete->where(
            fn (Builder $q) => $q
                ->where('created_by', $utilisateur->id)
                ->orWhereHas('assignees', fn (Builder $a) => $a->whereKey($utilisateur->id))
        );
    }
}
