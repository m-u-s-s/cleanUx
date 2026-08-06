<?php

namespace App\Services\Organizations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * REJOINDRE UNE SOCIÉTÉ, C'EST DEVENIR MEMBRE *ET* POUVOIR TRAVAILLER.
 *
 * POURQUOI CE SERVICE EXISTE. `TeamManagement::invite()` créait un `OrganizationMember` et rien
 * d'autre. Or `ProviderDashboard::mount()` exige `isProviderCompanyWorker()`, qui repose sur un
 * `ProviderProfile` de type `company_worker`. L'employé rejoignait donc bien la société, puis
 * recevait un 403 sur son écran principal — un état à moitié créé, impossible à diagnostiquer
 * depuis l'interface.
 *
 * Les deux écritures sont désormais indissociables et dans une transaction : on ne peut plus
 * obtenir l'une sans l'autre. Deux appelants s'en servent — l'invitation d'un utilisateur déjà
 * inscrit, et l'acceptation d'une invitation par lien.
 */
class OrganizationMembershipService
{
    /**
     * Rattache un utilisateur à une organisation, avec le profil prestataire qui va avec lorsque
     * l'organisation fournit du service.
     *
     * Idempotent : rejouer l'opération ne duplique ni le membre ni le profil.
     */
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
                ProviderProfile::firstOrCreate(
                    ['user_id' => $utilisateur->id],
                    [
                        'organization_account_id' => $organisation->id,
                        'provider_type' => ProviderType::COMPANY_WORKER->value,
                        'status' => 'active',
                    ],
                );
            }

            /*
             * Sans organisation courante, l'utilisateur retombe sur son espace particulier et ne
             * voit rien de la société qu'il vient de rejoindre. On ne l'impose qu'à ceux qui n'en
             * ont pas encore : basculer quelqu'un déjà rattaché ailleurs serait une surprise.
             */
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
