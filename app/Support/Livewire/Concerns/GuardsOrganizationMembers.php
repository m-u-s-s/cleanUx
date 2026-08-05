<?php

namespace App\Support\Livewire\Concerns;

use App\Enums\OrganizationRole;
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
 * D'où une seule porte d'entrée : `memberSousGarde()`.
 *
 * ELLE ÉCHOUE BRUYAMMENT, par `abort()` et `findOrFail()`. J'avais d'abord choisi un retour `null`
 * silencieux ; trois tests préexistants de `MembersAccess` ont montré que la convention de ce
 * dépôt est l'inverse — un refus se signale par un 403, un identifiant introuvable par une
 * `ModelNotFoundException`. Cette convention est la bonne : une action refusée en silence est
 * indiscernable d'une action réussie, aussi bien pour l'utilisateur que pour le journal. Et la
 * requête étant déjà limitée à l'organisation active, un 404 ne révèle rien sur ce qui existe
 * ailleurs.
 *
 * LA PROTECTION DU DERNIER PROPRIÉTAIRE est à part : elle ne dépend pas de l'acteur mais de
 * l'état de l'organisation. Une société sans propriétaire actif n'a plus personne pour inviter,
 * facturer ou céder ses droits — l'enfermement serait définitif.
 */
trait GuardsOrganizationMembers
{
    /**
     * Le membre visé, s'il appartient à l'organisation active de l'acteur ET que celui-ci a le
     * droit d'agir sur lui.
     *
     * Un refus de droit lève TOUJOURS un 403 : c'est une décision, elle doit se voir.
     *
     * L'identifiant introuvable, lui, admet deux traitements — et les deux existaient déjà dans le
     * dépôt, méthode par méthode. `changeRole()` faisait `findOrFail()` (exception), tandis que les
     * bascules de permission utilisaient `find()` puis un retour discret, parce qu'elles sont
     * pilotées par un état d'interface qui peut légitimement être périmé. D'où ce drapeau, qui
     * préserve chaque contrat au lieu d'en imposer un seul.
     *
     * @param  bool  $silencieuxSiIntrouvable  rendre `null` au lieu de lever, quand la cible
     *                                         n'existe pas dans l'organisation active
     */
    protected function memberSousGarde(
        ?int $memberId,
        string $permission,
        bool $silencieuxSiIntrouvable = false,
    ): ?OrganizationMember {
        if ($memberId === null) {
            return null;
        }

        $acteur = Auth::user();

        abort_unless(
            $acteur instanceof User && $acteur->current_organization_id !== null,
            403
        );

        // Le scoping fait partie de la REQUÊTE, pas d'une vérification après coup : un membre
        // d'une autre organisation n'est jamais chargé, donc jamais divulgué.
        $requete = OrganizationMember::query()
            ->where('id', $memberId)
            ->where('organization_account_id', $acteur->current_organization_id);

        $cible = $silencieuxSiIntrouvable ? $requete->first() : $requete->firstOrFail();

        if ($cible === null) {
            return null;
        }

        $permissions = app(PermissionService::class);

        abort_unless(
            $permissions->can($acteur, $permission, $acteur->currentOrganization),
            403
        );

        $membreActeur = $this->membreDeLActeur($acteur);

        abort_unless($membreActeur instanceof OrganizationMember, 403);

        // Agir sur soi-même reste permis pour les actions qui le supportent (le composant décide) ;
        // c'est la hiérarchie ENTRE personnes distinctes que cette garde protège.
        abort_if(
            $membreActeur->id !== $cible->id && ! $permissions->canManageMember($membreActeur, $cible),
            403
        );

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
        /*
         * `$membre->role` est CASTÉ EN ENUM par le modèle : le comparer à la chaîne `'owner'` est
         * toujours faux, et la garde ne s'armerait jamais. C'est précisément le défaut qui rendait
         * `PermissionService::canManageMember()` inopérante — je l'ai reproduit ici en l'écrivant,
         * et le test du dernier propriétaire l'a attrapé.
         *
         * J'avais alors ajouté une normalisation acceptant les deux formes ; PHPStan a montré
         * qu'elle était morte. On compare donc directement enum à enum, ce que le cast garantit.
         */
        if ($membre->role !== OrganizationRole::OWNER || $membre->status !== 'active') {
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
