<?php

namespace App\Support\Livewire\Concerns;

use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Organizations\OrganizationMemberAdministration;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;

/** RÉSOUDRE UN MEMBRE VISÉ PAR UNE ACTION, SANS FAIRE CONFIANCE À L'IDENTIFIANT REÇU. */
trait GuardsOrganizationMembers
{
    /**
     * Le membre visé, s'il appartient à l'organisation active de l'acteur ET que celui-ci a le droit d'agir sur lui.
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

    /** Retirer ce membre laisserait-il l'organisation sans propriétaire actif ? */
    protected function estLeDernierProprietaire(OrganizationMember $membre): bool
    {
        // LA RÈGLE A DÉMÉNAGÉ, ELLE N'A PAS ÉTÉ RECOPIÉE.
        return app(OrganizationMemberAdministration::class)->estLeDernierProprietaire($membre);
    }
}
