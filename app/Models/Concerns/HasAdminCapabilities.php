<?php

namespace App\Models\Concerns;

use Illuminate\Support\Collection;

trait HasAdminCapabilities
{
    public const ROLE_ADMIN = 'admin';

    public const PLATFORM_USER = 'user';

    public const PLATFORM_ADMIN = 'admin';

    public const PLATFORM_SUPER_ADMIN = 'super_admin';

    public const ACCESS_SCOPE_ALL = 'all';

    public const ACCESS_SCOPE_OWN = 'own';

    public const ACCESS_SCOPE_ORGANIZATION = 'organization';

    public const ACCESS_SCOPE_GLOBAL = 'global';

    public const ACCESS_SCOPE_ZONE = 'zone';

    public const ACCESS_SCOPE_READONLY = 'readonly';

    public static function allowedAdminPermissions(): array
    {
        return [
            'manage-calendar' => 'Gestion calendrier',
            'manage-users' => 'Gestion utilisateurs',
            'manage-services' => 'Gestion services',
            'manage-entreprises' => 'Gestion entreprises',
            'manage-finance' => 'Gestion finance',

            /*
             * COMPTABILITÉ ET FISCALITÉ — une capacité À PART de « Gestion finance », et ce n'est
             * pas un doublon.
             *
             * « Finance » ouvre les flux d'exploitation : versements, litiges, gestes commerciaux,
             * crédits clients. La comptabilité, elle, ouvre le grand livre, la clôture des
             * périodes, les exports légaux et la position de TVA — un métier différent, exercé par
             * quelqu'un d'extérieur à l'exploitation.
             *
             * Les séparer permet de donner un compte au comptable SANS lui ouvrir la trésorerie
             * opérationnelle, et sans obliger à faire de lui un super-administrateur.
             */
            'manage-accounting' => 'Comptabilité & fiscalité',
            'manage-analytics' => 'Analytics',
            'manage-quality' => 'Qualité',
            'manage-premium' => 'Clients premium',
            'manage-audit-logs' => 'Logs d\'audit',
            'manage-modules' => 'Modules plateforme',
            'manage-face-check' => 'Vérification faciale',
            'manage-international' => 'Opérations internationales',
            'manage-orchestration' => 'Orchestration terrain',
            'manage-automation' => 'Automatisation',

            /*
             * TROIS DOMAINES QUI N'AVAIENT AUCUNE CAPACITE, et dont les ecrans etaient donc
             * ouverts a tout administrateur.
             *
             * La liste d'origine a ete ecrite avant que ces modules n'existent. Plutot que de les
             * ranger de force sous une capacite voisine -- une correspondance approximative masque
             * un ecran a quelqu'un qui y a droit, ce qui est une regression silencieuse -- on
             * nomme ce qu'ils sont.
             *
             * `manage-compliance` : RGPD, KYC, KYB, assurance, score de risque. Le metier de la
             * conformite, distinct de la lecture des journaux d'audit.
             *
             * `manage-communication` : chat, e-mails, push, SMS, temps reel, preferences de
             * notification. Ecrire aux clients et aux prestataires n'est ni du marketing ni de
             * l'exploitation terrain.
             *
             * `manage-platform` : jetons d'API, geolocalisation, catalogue geographique, webhooks
             * sortants, outils, etat de preparation. L'infrastructure, distincte des « Modules
             * plateforme » qui n'activent que des fonctionnalites.
             */
            'manage-compliance' => 'Conformite (RGPD, KYC, KYB)',
            'manage-communication' => 'Communication & notifications',
            'manage-platform' => 'Infrastructure plateforme',

            /*
             * NOS LOCATIONS — une capacite a part, et pas un sous-ensemble de la flotte.
             *
             * Fleet est un registre d'employeur : ce qu'une societe confie a ses executants pour
             * aller travailler. Ici, chaque vehicule est un PRODUIT vendu a un client, avec un
             * prix, une caution et une garantie. Ce sont deux metiers, et rien ne dit que la meme
             * personne les tient : on peut confier le comptoir de location sans ouvrir la gestion
             * de parc interne, et l'inverse.
             */
            'manage-rentals' => 'Nos locations (vehicules)',
            'perform-critical-admin-actions' => 'Actions critiques',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return in_array($this->platform_role, [self::PLATFORM_ADMIN, self::PLATFORM_SUPER_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->platform_role === self::PLATFORM_SUPER_ADMIN;
    }

    public function canAccessAdminModule(?string $permission = null): bool
    {
        $isAdmin = in_array($this->platform_role, ['admin', 'super_admin'], true);

        if (! $isAdmin) {
            return false;
        }

        if (isset($this->is_active) && ! $this->is_active) {
            return false;
        }

        if (($this->platform_role ?? null) === 'super_admin'
            || ($this->is_super_admin ?? false)) {
            return true;
        }

        if ($permission === null) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [$permissions];
        }

        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }

        if (! is_array($permissions)) {
            $permissions = [];
        }

        $aliases = [$permission];

        $aliasMap = [
            'manage-modules' => ['manage_modules', 'admin.modules', 'modules.manage', 'platform.modules.manage', 'platform_modules.manage'],
        ];

        if (isset($aliasMap[$permission])) {
            $aliases = array_merge($aliases, $aliasMap[$permission]);
        }

        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $permissions) && (bool) $permissions[$alias]) {
                return true;
            }

            if (in_array($alias, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasAdminPermission(string $permission): bool
    {
        if ($this->is_super_admin ?? false) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }

        if (! is_array($permissions)) {
            return false;
        }

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        return array_key_exists($permission, $permissions) && (bool) $permissions[$permission];
    }

    public function canPerformCriticalAdminActions(): bool
    {
        return $this->canAccessAdminModule('perform-critical-admin-actions') && ! $this->isReadOnlyAdmin();
    }

    public function isReadOnlyAdmin(): bool
    {
        return ($this->platform_role ?? null) === 'readonly_admin'
            || ($this->access_scope ?? null) === self::ACCESS_SCOPE_READONLY
            || ($this->access_scope ?? null) === 'readonly';
    }

    public function isZoneScopedAdmin(): bool
    {
        return in_array($this->platform_role ?? null, ['admin', 'super_admin'], true)
            && (
                ($this->access_scope ?? null) === 'zone'
                || ! empty($this->managed_service_zone_id)
            );
    }

    public function permissionList(): array
    {
        $permissions = $this->permissions ?? [];

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if ($permissions instanceof Collection) {
            $permissions = $permissions->all();
        }

        return is_array($permissions) ? array_values((array) $permissions) : [];
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->isAdmin();
    }
}
