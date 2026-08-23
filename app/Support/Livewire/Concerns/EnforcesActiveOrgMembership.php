<?php

namespace App\Support\Livewire\Concerns;

use App\Models\OrganizationMember;
use Illuminate\Support\Facades\Auth;

/** P0.1 — multi-tenant guard for organisation (entreprise) Livewire dashboards. */
trait EnforcesActiveOrgMembership
{
    public function bootEnforcesActiveOrgMembership(): void
    {
        $user = Auth::user();
        abort_if($user === null, 403);

        // L'organisation active vit dans DEUX colonnes (`organization_account_id` et `current_organization_id`), plus un repli dans `metadata`.
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
