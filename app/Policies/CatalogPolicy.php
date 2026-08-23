<?php

namespace App\Policies;

use App\Models\User;

/** Qui a le droit d'écrire le catalogue de commande. UNE règle, à UN endroit. */
class CatalogPolicy
{
    /** Regarder le catalogue : réservé à l'administration. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Écrire : administrateur de plein exercice. */
    public function update(User $user): bool
    {
        return $user->isAdmin() && ! $user->isReadOnlyAdmin();
    }

    /** Archiver retire du catalogue sans rien détruire : même exigence qu'une écriture. */
    public function archive(User $user): bool
    {
        return $this->update($user);
    }

    /** Publier fige une révision qui devient le contrat de prix opposable aux clients suivants. */
    public function publish(User $user): bool
    {
        return $this->update($user);
    }
}
