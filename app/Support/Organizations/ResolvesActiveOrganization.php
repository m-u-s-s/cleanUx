<?php

namespace App\Support\Organizations;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;

/** QUELLE ORGANISATION SERT UNE REQUÊTE D'ESPACE SOCIÉTÉ — UNE SEULE RÈGLE, POUR LES DEUX CÔTÉS. */
trait ResolvesActiveOrganization
{
    /** L'organisation de l'appelant, ou 403. */
    protected function organisationActive(): OrganizationAccount
    {
        /** @var User|null $user */
        $user = auth()->user();

        $organisationId = $user?->organizationContextId();

        abort_if($user === null || $organisationId === null, 403);

        $estMembreActif = OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($estMembreActif, 403);

        $organisation = OrganizationAccount::query()->find($organisationId);

        abort_if($organisation === null, 403);

        return $organisation;
    }
}
