<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
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

        // 2. Permissions par défaut du rôle
        $rolePermissions = self::ROLE_PERMISSIONS[$member->role] ?? [];

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
        $rolePerms = self::ROLE_PERMISSIONS[$member->role] ?? [];
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
    public function invalidateCache(int $userId, int $orgId): void
    {
        $allPerms = $this->allPermissionKeys();

        foreach ($allPerms as $perm) {
            Cache::forget("perm.{$userId}.{$orgId}.{$perm}");
        }
    }

    /**
     * L'utilisateur peut-il gérer un autre membre selon la hiérarchie ?
     */
    public function canManageMember(
        OrganizationMember $actor,
        OrganizationMember $target
    ): bool {
        $actorRole  = OrganizationRole::from($actor->role);
        $targetRole = OrganizationRole::from($target->role);

        return $actorRole->canManage($targetRole);
    }
}
