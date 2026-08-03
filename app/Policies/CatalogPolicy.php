<?php

namespace App\Policies;

use App\Models\User;

/**
 * Qui a le droit d'écrire le catalogue de commande.
 *
 * UNE règle, à UN endroit. Elle vivait en trois exemplaires — la middleware de route, le trait
 * `EnforcesAdminAccess`, et une garde `refusesWrite()` recopiée dans deux composants. Trois copies
 * d'une même règle finissent par diverger, et c'est alors la plus permissive qui décide, sans que
 * personne ne s'en aperçoive.
 *
 * Les gardes existantes ne disparaissent pas : elles CONSULTENT désormais celle-ci au lieu de la
 * redire. La défense en profondeur reste — route, composant, action — mais sur une seule source.
 *
 * Une seule Policy pour les cinq modèles du catalogue : secteur, métier, question, option,
 * condition. Ils partagent exactement la même règle, et écrire cinq classes identiques n'aurait
 * fait que multiplier les endroits où elle peut dériver.
 */
class CatalogPolicy
{
    /**
     * Regarder le catalogue : réservé à l'administration.
     *
     * Le lecteur seul en fait partie — c'est précisément ce qu'il vient faire.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Écrire : administrateur de plein exercice.
     *
     * `isAdmin()` s'arrête à « est-ce un administrateur » ; un `platform_role` à « admin » assorti
     * d'un `access_scope` à « readonly » le satisfait. C'est cette nuance-là que les trois copies
     * précédentes rendaient chacune à leur façon.
     */
    public function update(User $user): bool
    {
        return $user->isAdmin()
            && ! (method_exists($user, 'isReadOnlyAdmin') && $user->isReadOnlyAdmin());
    }

    /** Archiver retire du catalogue sans rien détruire : même exigence qu'une écriture. */
    public function archive(User $user): bool
    {
        return $this->update($user);
    }

    /**
     * Publier fige une révision qui devient le contrat de prix opposable aux clients suivants.
     *
     * C'est le geste le plus lourd de l'écran ; il partage la règle d'écriture, et le dire
     * explicitement laisse la possibilité de la durcir sans toucher au reste.
     */
    public function publish(User $user): bool
    {
        return $this->update($user);
    }
}
