<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationRolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** Service central d'autorisation Brio. */
class PermissionService
{
    // ──────────────────────────────────────────────────────
    // Matrice des permissions par rôle
    // ──────────────────────────────────────────────────────

    /** La matrice par défaut — troisième et dernier étage de résolution. */
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
            'agencies.view',
            'agencies.manage',
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
            'missions.reschedule',
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
            // E23 — l'inventaire des consommables. `view` pour savoir ce qui reste, `manage` pour
            // réceptionner, corriger, et recevoir les alertes de réappro : prévenir quelqu'un qui
            // ne peut pas commander ferait du bruit sans recours.
            'inventory.view',
            'inventory.manage',
            // E24 — les devis que la societe batit elle-meme.
            'quotes.view',
            'quotes.manage',
            // E25 — le recrutement. Publier une offre et trier des candidatures touche a des
            // donnees personnelles de gens qui ne sont pas encore de la maison.
            'recruitment.view',
            'recruitment.manage',
            // E27 — la flotte de la societe. Fleet v2 existait, pilote par la seule plateforme.
            'fleet.view',
            'fleet.manage',
        ],

        OrganizationRole::MANAGER->value => [
            'inventory.view',
            'inventory.manage',
            'bookings.create',
            'bookings.view_all',
            'bookings.approve',
            'bookings.cancel',
            // DEUX ÉCRITURES SANS LEUR LECTURE — le même défaut que `sites.edit` chez le responsable de site, corrigé plus bas dans ce fichier.
            'missions.view_all',
            'missions.reschedule',
            'team.view',
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
            'quotes.view',
            'quotes.manage',
            'recruitment.view',
            'recruitment.manage',
            'fleet.view',
            'fleet.manage',
        ],

        OrganizationRole::SITE_MANAGER->value => [
            'bookings.create',
            'bookings.cancel',
            'sites.edit',
            // `sites.view_all` MANQUAIT, ET `sites.edit` était donc une clé morte : le responsable de site pouvait modifier un local sur un écran qu'il ne pouvait pas ouvrir — `SiteManager::mount()` répond 403 sans elle.
            'sites.view_all',
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
            // E23 — c'est le rôle qui commande réellement les produits.
            'inventory.view',
            'inventory.manage',
            'bookings.view_all',
            'bookings.approve',
            'bookings.cancel',
            'missions.assign',
            'missions.dispatch',
            'missions.view_all',
            'missions.quality',
            'missions.reschedule',
            // `sites.view_all` etait DECLAREE par `SiteOperations` et accordee a personne :
            // l'ecran existait, sa garde ne pouvait etre satisfaite. Ajout purement additif.
            'sites.view_all',
            'agencies.view',
            'agencies.manage',
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
            'quotes.view',
            // C'est le role qui repond aux appels d'offres : lui refuser `manage` ferait remonter
            // chaque chiffrage au proprietaire, c'est-a-dire nulle part le jour ou il est absent.
            'quotes.manage',
            // E27 — il commande les vehicules comme il commande les produits.
            'fleet.view',
            'fleet.manage',
        ],

        OrganizationRole::DISPATCHER->value => [
            'bookings.view_all',
            'missions.assign',
            'missions.dispatch',
            'missions.view_all',
            // Décaler d'une heure fait partie du métier de répartiteur : c'est lui qui voit
            // l'embouteillage arriver. La fenêtre de gel borne ce droit à 24 h de l'échéance.
            'missions.reschedule',
            'sites.view_all',
            // Lecture seule : le répartiteur FILTRE par implantation, il ne redessine pas
            // l'organigramme de la société.
            'agencies.view',
            'team.view',
            'channels.create',
            'tasks.create',
            'tasks.assign',
            'analytics.view',
            // Lecture seule : le repartiteur doit savoir quel camion est disponible, il ne decide
            // pas d'en acheter un.
            'fleet.view',
        ],

        OrganizationRole::TEAM_LEAD->value => [
            'missions.view_all',
            // Le chef d'equipe reassigne — c'est l'exigence 5.
            'missions.assign',
            'team.view',
            'channels.create',
            'tasks.create',
            'tasks.assign',
        ],

        OrganizationRole::QUALITY_MANAGER->value => [
            'missions.view_all',
            'missions.quality',
            // E27 — les certifications de ses collegues sont une donnee qualite : c'est lui qui
            // voit venir l'echeance avant que le moteur ne refuse l'assignation.
            'fleet.view',
            'analytics.view',
            'analytics.export',
            'channels.create',
        ],

        OrganizationRole::WORKER->value => [
            'channels.create',
            'tasks.create',
            // LE TERRAIN VOIT LE STOCK, IL NE LE GÈRE PAS (F7).
            'inventory.view',
        ],

        OrganizationRole::VIEWER->value => [
            'bookings.view_all',
            'analytics.view',
        ],
    ];

    // ──────────────────────────────────────────────────────
    // API publique
    // ──────────────────────────────────────────────────────

    /** L'utilisateur a-t-il la permission sur cette organisation ? */
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

    /** Vérifier la permission directement sur un OrganizationMember. */
    public function memberCan(OrganizationMember $member, string $permission): bool
    {
        // 1. Permissions personnalisées sur le membre (JSON) ont priorité
        $customPermissions = $member->permissions ?? [];

        if (array_key_exists($permission, $customPermissions)) {
            return (bool) $customPermissions[$permission];
        }

        $role = $member->role instanceof \BackedEnum ? $member->role->value : $member->role;

        // 2. MATRICE PROPRE À L'ORGANISATION (ajouté le 2026-08-06).
        // Pas d'organisation, pas de réglage d'organisation : on n'interroge la base que lorsque la question a un sens.
        // On lit l'ATTRIBUT BRUT plutôt que la propriété typée : le modèle la déclare non nullable, ce qui vaut pour une instance hydratée depuis la base, mais pas pour un `new OrganizationMember` construit en mémoire — où elle est simplement absente.
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
        $rolePermissions = self::permissionsParDefaut($role);

        return in_array($permission, $rolePermissions, true);
    }

    /**
     * Les permissions par défaut d'un rôle, ou aucune si le rôle est inconnu.
     *
     * @return list<string>
     */
    private static function permissionsParDefaut(string $role): array
    {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    /**
     * Retourner toutes les permissions d'un membre (rôle + matrice société + dérogations).
     *
     * @return array<string, bool>
     */
    public function allPermissionsFor(OrganizationMember $member): array
    {
        $role = $member->role instanceof \BackedEnum ? $member->role->value : $member->role;
        $rolePerms = self::permissionsParDefaut($role);
        $customPerms = $member->permissions ?? [];

        $orgId = (int) $member->getAttribute('organization_account_id');

        /**
         * Même précaution que dans `memberCan()` : un membre construit en mémoire — ce que font les tests unitaires de ce service — n'a pas d'organisation, donc pas de matrice à interroger.
         *
         * @var array<string, bool> $matriceSociete
         */
        $matriceSociete = $orgId > 0
            ? OrganizationRolePermission::query()
                ->where('organization_account_id', $orgId)
                ->where('role', $role)
                ->pluck('granted', 'permission')
                ->map(fn ($accorde) => (bool) $accorde)
                ->all()
            : [];

        $result = [];

        foreach ($this->allPermissionKeys() as $perm) {
            if (array_key_exists($perm, $customPerms)) {
                $result[$perm] = (bool) $customPerms[$perm];

                continue;
            }

            if (array_key_exists($perm, $matriceSociete)) {
                $result[$perm] = $matriceSociete[$perm];

                continue;
            }

            $result[$perm] = in_array($perm, $rolePerms, true);
        }

        return $result;
    }

    /** Ce rôle a-t-il cette permission SANS aucun réglage de société ni dérogation ? */
    public function roleAccordeParDefaut(string $role, string $permission): bool
    {
        return in_array($permission, self::permissionsParDefaut($role), true);
    }

    /**
     * Les clés ACCORDÉES à un membre — ce que le mobile reçoit.
     *
     * @return list<string>
     */
    public function grantedKeysFor(OrganizationMember $member): array
    {
        return array_keys(array_filter($this->allPermissionsFor($member)));
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

    /** Invalider le cache des permissions d'un utilisateur sur une organisation. */
    /** Purge le cache de TOUS les membres d'une organisation. */
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

    /** L'utilisateur peut-il gérer un autre membre selon la hiérarchie ? */
    public function canManageMember(
        OrganizationMember $actor,
        OrganizationMember $target
    ): bool {
        return $this->roleDe($actor)->canManage($this->roleDe($target));
    }

    /** Le rôle d'un membre. */
    private function roleDe(OrganizationMember $membre): OrganizationRole
    {
        return $membre->role;
    }
}
