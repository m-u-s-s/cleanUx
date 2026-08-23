<?php

namespace App\Support\Organizations;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;

/** CE QUE LE SERVEUR DÉCLARE À L'APPLICATION SUR L'ORGANISATION DE L'APPELANT. */
class ContratDeRoleMobile
{
    public function __construct(private PermissionService $permissions) {}

    /**
     * @return array{
     * organization_account_id: int|null,
     * organization_type: string|null,
     * organization_role: string|null,
     * organization_permissions: list<string>,
     * can_manage_company: bool
     * }
     */
    public function pour(User $utilisateur): array
    {
        // `organizationContextId()` et non `currentOrganization` : la seconde ne lit que `current_organization_id`, que les seeders ne renseignent pas toujours.
        $organisationId = $utilisateur->organizationContextId();

        $organisation = $organisationId !== null
            ? OrganizationAccount::query()->find($organisationId)
            : null;

        $membre = $organisationId !== null
            ? OrganizationMember::query()
                ->where('organization_account_id', $organisationId)
                ->where('user_id', $utilisateur->id)
                ->where('status', 'active')
                ->first()
            : null;

        return [
            'organization_account_id' => $organisationId,

            // `organization_accounts.type` n'est pas casté en enum : c'est une chaîne.
            'organization_type' => $organisation?->type,

            // Le sous-rôle n'était PAS exposé : l'application ne pouvait ni l'afficher ni distinguer un nettoyeur d'un dispatcheur autrement que par `can_manage_company`.
            'organization_role' => $membre?->role->value,

            // L'ADHÉSION ACTIVE FAIT FOI.
            'organization_permissions' => $membre !== null
                ? $this->permissions->grantedKeysFor($membre)
                : [],

            // CONSERVÉ TEL QUEL.
            'can_manage_company' => $organisation !== null
                && $this->permissions->can($utilisateur, 'missions.view_all', $organisation),
        ];
    }
}
