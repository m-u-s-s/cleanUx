<?php

namespace App\Models\Concerns;

use App\Support\Platform\PorteDuSiege;
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

    /**
     * LE SIEGE NE SE POSE QUE PAR SA PORTE.
     *
     * Eloquent appelle `boot{NomDuTrait}` : la garde vit donc avec la notion qu'elle protege.
     * Elle attrape ce qu'aucun middleware ne voit — un seeder, un `forceFill`, la console
     * d'administration, `/livewire/update` qui ne rejoue aucun middleware de route.
     *
     * La base refuse deja un SECOND siege ; ce crochet refuse en plus qu'on DEPLACE le siege
     * sans passer par le service, et donne un message qui explique au lieu d'une violation
     * d'index brute.
     */
    public static function bootHasAdminCapabilities(): void
    {
        static::saving(function (self $utilisateur) {
            $devientSuperAdmin = $utilisateur->platform_role === self::PLATFORM_SUPER_ADMIN
                && $utilisateur->getOriginal('platform_role') !== self::PLATFORM_SUPER_ADMIN;

            // ET LA PERTE DU SIEGE EST GARDEE COMME SA PRISE. Ne garder que la promotion
            // laisserait un vol en DEUX temps : retrograder le titulaire, puis se promouvoir
            // sur un siege devenu vacant. Chacune des deux ecritures est donc refusee.
            $perdLeSiege = $utilisateur->exists
                && $utilisateur->getOriginal('platform_role') === self::PLATFORM_SUPER_ADMIN
                && $utilisateur->platform_role !== self::PLATFORM_SUPER_ADMIN;

            if (($devientSuperAdmin || $perdLeSiege) && ! PorteDuSiege::estOuverte()) {
                throw new \DomainException(
                    'Le siege de super-administrateur ne se deplace que par SiegeDuSuperAdmin.'
                );
            }

            // LA SECONDE NOTION DEVIENT UN MIROIR. `is_super_admin` ouvrait
            // `hasAdminPermission()` a elle seule : deux verites pour un fait, donc un second
            // super-administrateur de fait des que quelqu'un posait la colonne.
            $utilisateur->is_super_admin = $utilisateur->platform_role === self::PLATFORM_SUPER_ADMIN;

            // LA PHRASE MEURT AVEC LE SIEGE : un ancien titulaire ne garde pas de quoi
            // reclamer celui de son successeur.
            if (! $utilisateur->is_super_admin) {
                $utilisateur->seat_secret_hash = null;
                $utilisateur->seat_claimed_at = null;
            }
        });
    }

    public static function allowedAdminPermissions(): array
    {
        return [
            'manage-calendar' => 'Gestion calendrier',
            'manage-users' => 'Gestion utilisateurs',
            'manage-services' => 'Gestion services',
            'manage-entreprises' => 'Gestion entreprises',
            'manage-finance' => 'Gestion finance',

            // COMPTABILITÉ ET FISCALITÉ — une capacité À PART de « Gestion finance », et ce n'est pas un doublon.
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

            // TROIS DOMAINES QUI N'AVAIENT AUCUNE CAPACITE, et dont les ecrans etaient donc ouverts a tout administrateur.
            'manage-compliance' => 'Conformite (RGPD, KYC, KYB)',
            'manage-communication' => 'Communication & notifications',
            'manage-platform' => 'Infrastructure plateforme',

            // NOS LOCATIONS — une capacite a part, et pas un sous-ensemble de la flotte.
            'manage-rentals' => 'Nos locations (vehicules)',

            // LA LOCATION ENTRE MEMBRES — arbitrer entre deux membres n'est pas gerer la
            // flotte de la plateforme : deux metiers, deux capacites.
            'manage-peer-rentals' => 'Location entre membres',
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

        if ($this->isSuperAdmin()) {
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
        // LE ROLE, PAS LA COLONNE. `is_super_admin` seule ouvrait TOUTES les permissions,
        // avant meme de verifier que le compte est administrateur : un seeder qui posait la
        // colonne sans le role creait un super-administrateur de fait, invisible.
        if ($this->isSuperAdmin()) {
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
