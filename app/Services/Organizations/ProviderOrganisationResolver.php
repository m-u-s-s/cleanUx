<?php

namespace App\Services\Organizations;

use Illuminate\Support\Facades\DB;

/** LA SOCIÉTÉ PRESTATAIRE D'UN COMPTE — une seule lecture, un seul endroit. */
class ProviderOrganisationResolver
{
    /** L'identifiant de la société prestataire du compte, ou `null`. */
    public function pourUtilisateur(?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        $organisation = DB::table('provider_profiles')
            ->where('user_id', $userId)
            ->value('organization_account_id');

        return $organisation === null ? null : (int) $organisation;
    }
}
