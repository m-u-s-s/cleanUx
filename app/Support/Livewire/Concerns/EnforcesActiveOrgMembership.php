<?php

namespace App\Support\Livewire\Concerns;

use App\Models\OrganizationMember;
use Illuminate\Support\Facades\Auth;

/**
 * P0.1 — multi-tenant guard for organisation (entreprise) Livewire dashboards.
 *
 * Company components operate on Auth::user()->current_organization_id, which is mass-assignable.
 * A role check (isClientCompany / isProviderCompany) is NOT enough: it does not prove the user is
 * a member of the org they're acting on (horizontal privilege escalation / IDOR).
 *
 * Livewire calls boot{TraitName} on EVERY request — initial mount AND every subsequent action —
 * so this validates membership on reads and writes alike. Aborts 403 unless the authenticated user
 * is an ACTIVE member of their current organisation.
 */
trait EnforcesActiveOrgMembership
{
    public function bootEnforcesActiveOrgMembership(): void
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        /*
            L'organisation active vit dans DEUX colonnes (`organization_account_id`
            et `current_organization_id`), plus un repli dans `metadata`.
            `organizationContextId()` est la résolution unique du dépôt, celle que
            la surface API applique déjà (`ResolvesActiveOrganization`).

            Lire `current_organization_id` seul refusait ici des membres
            parfaitement légitimes — « Aucune organisation active » sur leur propre
            espace — pendant que la même personne passait par l'API.

            L'appartenance, elle, reste vérifiée exactement comme avant : c'est
            l'IDENTIFIANT qu'on résout mieux, pas le droit qu'on relâche.
        */
        $orgId = $user->organizationContextId();
        abort_if(empty($orgId), 403, 'Aucune organisation active.');

        abort_unless(
            OrganizationMember::query()
                ->where('organization_account_id', $orgId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists(),
            403,
            "Accès refusé : vous n'êtes pas membre actif de cette organisation.",
        );
    }
}
