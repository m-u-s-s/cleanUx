<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Service central d'autorisation CleanUx.
 *
 * Toute vérification de permission passe ici — jamais directement
 * sur les colonnes du modèle User ou OrganizationMember.
 *
 * Permissions disponibles (par domaine) :
 *
 * BOOKINGS
 *   bookings.create         Créer une réservation
 *   bookings.view_all       Voir toutes les réservations de l'org
 *   bookings.approve        Approuver les demandes en attente
 *   bookings.cancel         Annuler une réservation
 *
 * SITES
 *   sites.create            Créer un nouveau local
 *   sites.edit              Modifier un local existant
 *   sites.delete            Supprimer un local
 *   sites.view_all          Voir tous les locaux (sinon : uniquement les assignés)
 *   sites.assign_members    Assigner des membres à un local
 *
 * MEMBERS
 *   members.invite          Inviter un nouveau membre
 *   members.edit_role       Changer le rôle d'un membre
 *   members.suspend         Suspendre un membre
 *   members.remove          Retirer un membre de l'organisation
 *   members.manage_permissions  Accorder ou retirer des permissions à un membre (propriétaire)
 *
 * FINANCE
 *   finance.view            Voir les factures et paiements
 *   finance.download        Télécharger les documents financiers
 *   finance.manage          Gérer Stripe Connect, virements
 *
 * MISSIONS (côté prestataire)
 *   missions.assign         Assigner une mission à un travailleur
 *   missions.dispatch       Dispatcher plusieurs missions en masse
 *   missions.view_all       Voir toutes les missions de l'org
 *   missions.quality        Accéder aux rapports qualité
 *
 * TEAM (côté prestataire)
 *   team.create             Créer une équipe
 *   team.manage             Gérer les membres d'une équipe
 *   team.view               Voir les équipes existantes
 *
 * COMMUNICATION
 *   channels.create         Créer un canal
 *   channels.manage         Archiver / supprimer un canal
 *   tasks.create            Créer une tâche
 *   tasks.assign            Assigner une tâche à un membre
 *   tasks.close             Clôturer / supprimer une tâche
 *
 * ANALYTICS
 *   analytics.view          Voir les statistiques de l'organisation
 *   analytics.export        Exporter les données
 *
 * ADMIN PLATEFORME (platform_role = admin / super_admin)
 *   platform.manage_users
 *   platform.manage_orgs
 *   platform.view_logs
 */
class PermissionService
{
    // ──────────────────────────────────────────────────────
    // Matrice des permissions par rôle
    // ──────────────────────────────────────────────────────

    private const ROLE_PERMISSIONS = [

        OrganizationRole::OWNER->value => [
            'bookings.create',
            'bookings.view_all',
            'bookings.approve',
            'bookings.cancel',
            'sites.create',
            'sites.edit',
            'sites.delete',
            'sites.view_all',
            'sites.assign_members',
            'members.invite',
            'members.edit_role',
            'members.suspend',
            'members.remove',
            // Distribuer des permissions n'est PAS inviter. Cette clé est volontairement réservée
            // au propriétaire : `TeamManagement` ne gardait ses écrans que par `members.invite`,
            // si bien qu'un gestionnaire pouvait s'attribuer n'importe quel droit.
            'members.manage_permissions',
            'finance.view',
            'finance.download',
            'finance.manage',
            'missions.assign',
            'missions.dispatch',
            'missions.view_all',
            'missions.quality',
            'team.create',
            'team.manage',
            'team.view',
            'channels.create',
            'channels.manage',
            'tasks.create',
            'tasks.assign',
            'tasks.close',
            'analytics.view',
            'analytics.export',
        ],

        OrganizationRole::MANAGER->value => [
            'bookings.create',
            'bookings.view_all',
            'bookings.approve',
            'bookings.cancel',
            'sites.create',
            'sites.edit',
            'sites.view_all',
            'sites.assign_members',
            'members.invite',
            'finance.view',
            'finance.download',
            'channels.create',
            'channels.manage',
            'tasks.create',
            'tasks.assign',
            'tasks.close',
            'analytics.view',
            'analytics.export',
        ],

        OrganizationRole::SITE_MANAGER->value => [
            'bookings.create',
            'bookings.cancel',
            'sites.edit',
            'tasks.create',
            'tasks.assign',
            'channels.create',
            'analytics.view',
        ],

        OrganizationRole::FINANCE->value => [
            'bookings.view_all',
            'finance.view',
            'finance.download',
            'analytics.view',
            'analytics.export',
        ],

        OrganizationRole::REQUESTER->value => [
            'bookings.create',
            'tasks.create',
        ],

        OrganizationRole::OPERATIONS_MANAGER->value => [
            'bookings.view_all',
            'bookings.approve',
            'bookings.cancel',
            'missions.assign',
            'missions.dispatch',
            'missions.view_all',
            'missions.quality',
            'team.create',
            'team.manage',
            'team.view',
            'members.invite',
            'channels.create',
            'channels.manage',
            'tasks.create',
            'tasks.assign',
            'tasks.close',
            'analytics.view',
            'analytics.export',
        ],

        OrganizationRole::DISPATCHER->value => [
            'bookings.view_all',
            'missions.assign',
            'missions.dispatch',
            'missions.view_all',
            'team.view',
            'channels.create',
            'tasks.create',
            'tasks.assign',
            'analytics.view',
        ],

        OrganizationRole::TEAM_LEAD->value => [
            'missions.view_all',
            'team.view',
            'channels.create',
            'tasks.create',
            'tasks.assign',
        ],

        OrganizationRole::QUALITY_MANAGER->value => [
            'missions.view_all',
            'missions.quality',
            'analytics.view',
            'analytics.export',
            'channels.create',
        ],

        OrganizationRole::WORKER->value => [
            'channels.create',
            'tasks.create',
        ],

        OrganizationRole::VIEWER->value => [
            'bookings.view_all',
            'analytics.view',
        ],
    ];

    // ──────────────────────────────────────────────────────
    // API publique
    // ──────────────────────────────────────────────────────

    /**
     * L'utilisateur a-t-il la permission sur cette organisation ?
     */
    public function can(User $user, string $permission, OrganizationAccount|int|null $organization = null): bool
    {
        if ($organization === null) {
            return false;
        }
        $orgId = $organization instanceof OrganizationAccount
            ? $organization->id
            : $organization;

        // Admins plateforme ont tout
        if (in_array($user->platform_role, ['admin', 'super_admin'], true)) {
            return true;
        }

        $cacheKey = "perm.{$user->id}.{$orgId}.{$permission}";

        return Cache::remember($cacheKey, 60, function () use ($user, $orgId, $permission) {
            $member = OrganizationMember::query()
                ->where('organization_account_id', $orgId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (! $member) {
                return false;
            }

            return $this->memberCan($member, $permission);
        });
    }

    /**
     * Vérifier la permission directement sur un OrganizationMember.
     */
    public function memberCan(OrganizationMember $member, string $permission): bool
    {
        // 1. Permissions personnalisées sur le membre (JSON) ont priorité
        $customPermissions = $member->permissions ?? [];

        if (array_key_exists($permission, $customPermissions)) {
            return (bool) $customPermissions[$permission];
        }

        $role = $member->role instanceof \BackedEnum ? $member->role->value : $member->role;

        /*
         * 2. MATRICE PROPRE À L'ORGANISATION (ajouté le 2026-08-06).
         *
         * `ROLE_PERMISSIONS` ci-dessous est une constante privée : la correspondance rôle →
         * permissions était figée dans le code. Un propriétaire pouvait accorder un droit à une
         * personne précise (étage 1), mais pas décider que « chez nous, les chefs d'équipe
         * assignent les missions » — cela réclamait un déploiement.
         *
         * `granted` est un booléen explicite, non une simple présence : une société peut donc
         * aussi RETIRER un droit accordé par défaut. Sans réglage, aucune ligne n'existe et la
         * résolution retombe intégralement sur l'étage 3 — le comportement reste identique.
         */
        /*
         * Pas d'organisation, pas de réglage d'organisation : on n'interroge la base que lorsque
         * la question a un sens. Sans cette condition, `memberCan()` exigeait une base de données
         * même pour un membre construit en mémoire — ce que font les tests unitaires de ce
         * service, et ce qui les a fait échouer alors que mes exécutions ciblées sur
         * `tests/Feature/ProviderCompany` étaient vertes.
         */
        /*
         * On lit l'ATTRIBUT BRUT plutôt que la propriété typée : le modèle la déclare non
         * nullable, ce qui vaut pour une instance hydratée depuis la base, mais pas pour un
         * `new OrganizationMember` construit en mémoire — où elle est simplement absente.
         */
        $orgIdDuMembre = (int) $member->getAttribute('organization_account_id');

        if ($orgIdDuMembre > 0) {
            $reglageSociete = OrganizationRolePermission::query()
                ->where('organization_account_id', $orgIdDuMembre)
                ->where('role', $role)
                ->where('permission', $permission)
                ->first();

            if ($reglageSociete !== null) {
                return $reglageSociete->granted;
            }
        }

        // 3. Permissions par défaut du rôle
        $rolePermissions = self::ROLE_PERMISSIONS[$role] ?? [];

        return in_array($permission, $rolePermissions, true);
    }

    /**
     * Retourner toutes les permissions d'un membre (rôle + overrides).
     *
     * @return array<string, bool>
     */
    public function allPermissionsFor(OrganizationMember $member): array
    {
        $allPerms = $this->allPermissionKeys();
        $role = $member->role instanceof \BackedEnum ? $member->role->value : $member->role;
        $rolePerms = self::ROLE_PERMISSIONS[$role] ?? [];
        $customPerms = $member->permissions ?? [];

        $result = [];

        foreach ($allPerms as $perm) {
            if (array_key_exists($perm, $customPerms)) {
                $result[$perm] = (bool) $customPerms[$perm];
            } else {
                $result[$perm] = in_array($perm, $rolePerms, true);
            }
        }

        return $result;
    }

    /**
     * Toutes les clés de permission disponibles dans la plateforme.
     *
     * @return string[]
     */
    public function allPermissionKeys(): array
    {
        return array_values(array_unique(
            array_merge(...array_values(self::ROLE_PERMISSIONS))
        ));
    }

    /**
     * Invalider le cache des permissions d'un utilisateur sur une organisation.
     */
    /**
     * Purge le cache de TOUS les membres d'une organisation.
     *
     * Modifier la matrice de la société change les droits de plusieurs personnes d'un coup :
     * purger le seul acteur laisserait les autres sur l'ancienne réponse pendant une minute — un
     * délai invisible et incompréhensible côté utilisateur.
     */
    public function invalidateOrganizationCache(int $orgId): void
    {
        OrganizationMember::query()
            ->where('organization_account_id', $orgId)
            ->pluck('user_id')
            ->each(fn ($userId) => $this->invalidateCache((int) $userId, $orgId));
    }

    public function invalidateCache(int $userId, int $orgId): void
    {
        $allPerms = $this->allPermissionKeys();

        foreach ($allPerms as $perm) {
            Cache::forget("perm.{$userId}.{$orgId}.{$perm}");
        }
    }

    /**
     * L'utilisateur peut-il gérer un autre membre selon la hiérarchie ?
     *
     * ELLE NE POUVAIT PAS FONCTIONNER (corrigé le 2026-08-05). Elle appelait
     * `OrganizationRole::from($actor->role)` alors que `OrganizationMember::$role` est DÉJÀ casté
     * en enum par le modèle — tout appel levait donc `TypeError: must be of type string|int,
     * OrganizationRole given`. C'est probablement pourquoi elle n'était appelée nulle part :
     * elle avait été écrite, jamais exercée, et la hiérarchie n'était appliquée par personne.
     *
     * `$role` est désormais accepté sous ses deux formes — l'enum du modèle comme la chaîne d'un
     * appelant qui lirait la colonne brute.
     */
    public function canManageMember(
        OrganizationMember $actor,
        OrganizationMember $target
    ): bool {
        return $this->roleDe($actor)->canManage($this->roleDe($target));
    }

    /**
     * Le rôle d'un membre.
     *
     * `OrganizationMember::$role` est CASTÉ en enum par le modèle : l'accès rend toujours un
     * `OrganizationRole`, jamais une chaîne. J'avais prévu les deux formes ; PHPStan a montré que
     * la seconde branche était inatteignable.
     *
     * Le bug d'origine n'était donc pas que le rôle pouvait être une chaîne, mais que le code
     * COMPARAIT cet enum à une chaîne littérale — l'inverse. Garder une conversion défensive ici
     * n'aurait rien protégé et aurait entretenu la confusion.
     */
    private function roleDe(OrganizationMember $membre): OrganizationRole
    {
        return $membre->role;
    }
}
