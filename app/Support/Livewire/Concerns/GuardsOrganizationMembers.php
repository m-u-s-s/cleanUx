<?php

namespace App\Support\Livewire\Concerns;

use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

/**
 * RÉSOUDRE UN MEMBRE VISÉ PAR UNE ACTION, SANS FAIRE CONFIANCE À L'IDENTIFIANT REÇU.
 *
 * POURQUOI CE TRAIT EXISTE. `TeamManagement` (prestataire) et `MembersAccess` (client) faisaient
 * tous deux `OrganizationMember::find($id)` sur un identifiant venu du client, puis agissaient.
 * Trois trous, identiques des deux côtés :
 *
 *   1. ISOLATION — rien ne rattachait le membre à l'organisation de l'acteur. Un identifiant
 *      appartenant à une AUTRE société était accepté : fuite entre clients.
 *   2. ESCALADE — les composants n'étaient gardés qu'au `mount()`, sur une permission large
 *      (`members.invite`). Le droit d'inviter suffisait donc à distribuer n'importe quel droit.
 *   3. HIÉRARCHIE — `PermissionService::canManageMember()` existait déjà et n'était appelée
 *      nulle part : on pouvait agir sur un membre de rang égal ou supérieur.
 *
 * D'où une seule porte d'entrée : `memberSousGarde()`. Elle rend `null` — jamais une exception —
 * pour que les composants Livewire restent silencieux face à un identifiant forgé plutôt que de
 * révéler par un 403 qu'il existe ailleurs.
 *
 * LA PROTECTION DU DERNIER PROPRIÉTAIRE est à part : elle ne dépend pas de l'acteur mais de
 * l'état de l'organisation. Une société sans propriétaire actif n'a plus personne pour inviter,
 * facturer ou céder ses droits — l'enfermement serait définitif.
 */
trait GuardsOrganizationMembers
{
    /**
     * Le membre visé, s'il appartient à l'organisation active de l'acteur ET que celui-ci a le
     * droit d'agir sur lui. `null` dans tous les autres cas.
     */
    protected function memberSousGarde(?int $memberId, string $permission): ?OrganizationMember
    {
        if ($memberId === null) {
            return null;
        }

        $acteur = Auth::user();

        if (! $acteur instanceof User || $acteur->current_organization_id === null) {
            return null;
        }

        // Le scoping fait partie de la REQUÊTE, pas d'une vérification après coup : un membre
        // d'une autre organisation n'est jamais chargé, donc jamais divulgué.
        $cible = OrganizationMember::query()
            ->where('id', $memberId)
            ->where('organization_account_id', $acteur->current_organization_id)
            ->first();

        if (! $cible instanceof OrganizationMember) {
            return null;
        }

        $permissions = app(PermissionService::class);

        if (! $permissions->can($acteur, $permission, $acteur->currentOrganization)) {
            return null;
        }

        $membreActeur = $this->membreDeLActeur($acteur);

        if (! $membreActeur instanceof OrganizationMember) {
            return null;
        }

        // Agir sur soi-même reste permis pour les actions qui le supportent (le composant décide) ;
        // c'est la hiérarchie ENTRE personnes distinctes que cette garde protège.
        if ($membreActeur->id !== $cible->id && ! $permissions->canManageMember($membreActeur, $cible)) {
            return null;
        }

        return $cible;
    }

    /** L'inscription de l'acteur dans son organisation active. */
    protected function membreDeLActeur(User $acteur): ?OrganizationMember
    {
        return OrganizationMember::query()
            ->where('organization_account_id', $acteur->current_organization_id)
            ->where('user_id', $acteur->id)
            ->first();
    }

    /**
     * Retirer ce membre laisserait-il l'organisation sans propriétaire actif ?
     *
     * Vaut pour la suppression, la suspension et le déclassement — trois façons de perdre le
     * dernier propriétaire.
     */
    protected function estLeDernierProprietaire(OrganizationMember $membre): bool
    {
        if ($membre->role !== 'owner' || $membre->status !== 'active') {
            return false;
        }

        return OrganizationMember::query()
            ->where('organization_account_id', $membre->organization_account_id)
            ->where('role', 'owner')
            ->where('status', 'active')
            ->where('id', '!=', $membre->id)
            ->doesntExist();
    }
}
