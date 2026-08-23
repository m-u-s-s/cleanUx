<?php

namespace App\Services\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Builder;

/** QUELLES TÂCHES CETTE PERSONNE A-T-ELLE LE DROIT DE VOIR ? */
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
