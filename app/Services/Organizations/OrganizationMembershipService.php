<?php

namespace App\Services\Organizations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** REJOINDRE UNE SOCIÉTÉ, C'EST DEVENIR MEMBRE *ET* POUVOIR TRAVAILLER. POURQUOI CE SERVICE EXISTE. */
class OrganizationMembershipService
{
    /** Rattache un utilisateur à une organisation, avec le profil prestataire qui va avec lorsque l'organisation fournit du service. */
    public function rattacher(
        OrganizationAccount $organisation,
        User $utilisateur,
        string $role,
        ?int $invitePar = null,
    ): OrganizationMember {
        return DB::transaction(function () use ($organisation, $utilisateur, $role, $invitePar) {
            $membre = OrganizationMember::updateOrCreate(
                [
                    'organization_account_id' => $organisation->id,
                    'user_id' => $utilisateur->id,
                ],
                [
                    'role' => $role,
                    'status' => 'active',
                    'invited_by' => $invitePar,
                    'invited_at' => now(),
                    'joined_at' => now(),
                ],
            );

            if ($this->fournitDuService($organisation)) {
                // `updateOrCreate` ET NON `firstOrCreate`.
                $profil = ProviderProfile::firstOrNew(['user_id' => $utilisateur->id]);

                $profil->organization_account_id = $organisation->id;
                // Le modèle caste cette colonne en énumération : lui passer la chaîne
                // fonctionnerait à l'exécution mais ment sur le type.
                $profil->provider_type = ProviderType::COMPANY_WORKER;
                $profil->status = $profil->exists ? $profil->status : 'active';
                $profil->save();
            }

            // Sans organisation courante, l'utilisateur retombe sur son espace particulier et ne voit rien de la société qu'il vient de rejoindre.
            if ($utilisateur->current_organization_id === null) {
                $utilisateur->forceFill([
                    'current_organization_id' => $organisation->id,
                ])->save();
            }

            return $membre;
        });
    }

    private function fournitDuService(OrganizationAccount $organisation): bool
    {
        // `type` est une colonne chaîne, pas un enum casté : `tryFrom` suffit et ne ment pas.
        $type = OrganizationType::tryFrom((string) $organisation->type);

        return in_array($type, [
            OrganizationType::PROVIDER_COMPANY,
            OrganizationType::PROVIDER_SOLO,
            OrganizationType::HYBRID,
        ], true);
    }
}
