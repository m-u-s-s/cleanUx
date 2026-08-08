<?php

namespace App\Support\Organizations;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;

/**
 * CE QUE LE SERVEUR DÉCLARE À L'APPLICATION SUR L'ORGANISATION DE L'APPELANT.
 *
 * DEUX RÉPONSES DISENT LA MÊME CHOSE, ET ELLES DIVERGEAIENT DÉJÀ. La connexion passe par
 * `ApiAuthController::serializeUser()`, la reprise de session par `AuthMeController` : l'une
 * n'envoyait ni `can_manage_company` ni `organization_type`, et les deux ne résolvaient pas
 * l'organisation de la même façon. Un compte pouvait donc ouvrir l'espace société après un
 * redémarrage de l'application et pas après une connexion — ou l'inverse. Le champ ajouté ici l'est
 * pour les deux, parce qu'il n'existe qu'à un seul endroit.
 *
 * LE MOBILE N'A PAS DE MATRICE. Recopier la correspondance rôle → permissions dans l'application
 * l'aurait fait diverger de `PermissionService` au premier ajustement, et une société qui règle sa
 * propre matrice en base n'aurait rien changé sur les téléphones. Le serveur envoie donc les clés
 * ACCORDÉES, telles qu'il les résout lui-même ; le client applique un défaut-refus.
 *
 * `organization_role` EST INFORMATIF, PAS DÉCISIONNEL. Il sert à afficher « Chef d'équipe » sous un
 * nom, pas à décider d'un accès : dès qu'un écran conditionne quelque chose, c'est une clé de
 * `organization_permissions` qu'il doit lire, sans quoi la matrice réglable ne servirait à rien.
 */
class ContratDeRoleMobile
{
    public function __construct(private PermissionService $permissions) {}

    /**
     * @return array{
     *     organization_account_id: int|null,
     *     organization_type: string|null,
     *     organization_role: string|null,
     *     organization_permissions: list<string>,
     *     can_manage_company: bool
     * }
     */
    public function pour(User $utilisateur): array
    {
        /*
         * `organizationContextId()` et non `currentOrganization` : la seconde ne lit que
         * `current_organization_id`, que les seeders ne renseignent pas toujours. Sur une base
         * fraîchement semée, le type revenait `null` et l'aiguillage société ne s'ouvrait pour
         * personne.
         */
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

            /*
             * `organization_accounts.type` n'est pas casté en enum : c'est une chaîne. Le drapeau
             * booléen `is_entreprise` ne suffisait pas — une société CLIENTE et une société
             * PRESTATAIRE ouvrent deux espaces différents.
             */
            'organization_type' => $organisation?->type,

            /*
             * Le sous-rôle n'était PAS exposé : l'application ne pouvait ni l'afficher ni
             * distinguer un nettoyeur d'un dispatcheur autrement que par `can_manage_company`.
             */
            'organization_role' => $membre?->role->value,

            /*
             * L'ADHÉSION ACTIVE FAIT FOI. Sans membre, aucune clé : une organisation résolue ne
             * prouve pas l'appartenance — un compte peut garder en base l'identifiant d'une société
             * qu'il a quittée.
             */
            'organization_permissions' => $membre !== null
                ? $this->permissions->grantedKeysFor($membre)
                : [],

            /*
             * CONSERVÉ TEL QUEL. Les applications déjà installées le lisent pour aiguiller vers
             * l'espace société ; le retirer les enfermerait hors de cet espace. Il vaut exactement
             * `missions.view_all`, qui figure désormais aussi dans la liste ci-dessus — le champ
             * survit comme raccourci historique, pas comme seconde source de vérité.
             */
            'can_manage_company' => $organisation !== null
                && $this->permissions->can($utilisateur, 'missions.view_all', $organisation),
        ];
    }
}
