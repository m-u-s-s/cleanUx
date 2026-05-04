<?php

namespace App\Enums;

enum OrganizationRole: string
{
    // ──────────────────────────────────────────────────────
    // Rôles partagés (owner existe des deux côtés)
    // ──────────────────────────────────────────────────────
    case OWNER   = 'owner';
    case FINANCE = 'finance';
    case VIEWER  = 'viewer';

    // ──────────────────────────────────────────────────────
    // Rôles entreprise CLIENTE
    // ──────────────────────────────────────────────────────
    case MANAGER      = 'manager';       // Gestionnaire général
    case SITE_MANAGER = 'site_manager';  // Responsable d'un ou plusieurs sites
    case REQUESTER    = 'requester';     // Peut uniquement créer des demandes

    // ──────────────────────────────────────────────────────
    // Rôles entreprise PRESTATAIRE
    // ──────────────────────────────────────────────────────
    case OPERATIONS_MANAGER = 'operations_manager'; // Directeur opérations
    case DISPATCHER         = 'dispatcher';          // Coordinateur / planificateur
    case TEAM_LEAD          = 'team_lead';           // Chef d'équipe terrain
    case WORKER             = 'worker';              // Nettoyeur / exécutant
    case QUALITY_MANAGER    = 'quality_manager';     // Responsable qualité

    // ──────────────────────────────────────────────────────
    // Helpers : label lisible
    // ──────────────────────────────────────────────────────
    public function label(): string
    {
        return match ($this) {
            self::OWNER              => 'Propriétaire',
            self::FINANCE            => 'Finance',
            self::VIEWER             => 'Lecteur',
            self::MANAGER            => 'Gestionnaire',
            self::SITE_MANAGER       => 'Responsable de site',
            self::REQUESTER          => 'Demandeur',
            self::OPERATIONS_MANAGER => 'Directeur opérations',
            self::DISPATCHER         => 'Coordinateur',
            self::TEAM_LEAD          => 'Chef d\'équipe',
            self::WORKER             => 'Nettoyeur',
            self::QUALITY_MANAGER    => 'Responsable qualité',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OWNER              => 'red',
            self::OPERATIONS_MANAGER => 'orange',
            self::MANAGER            => 'blue',
            self::DISPATCHER         => 'cyan',
            self::SITE_MANAGER       => 'purple',
            self::TEAM_LEAD          => 'yellow',
            self::QUALITY_MANAGER    => 'green',
            self::FINANCE            => 'emerald',
            self::REQUESTER          => 'indigo',
            self::WORKER             => 'slate',
            self::VIEWER             => 'gray',
        };
    }

    // ──────────────────────────────────────────────────────
    // Rôles disponibles selon le type d'organisation
    // ──────────────────────────────────────────────────────

    /** @return self[] */
    public static function forClientCompany(): array
    {
        return [
            self::OWNER,
            self::MANAGER,
            self::SITE_MANAGER,
            self::FINANCE,
            self::REQUESTER,
            self::VIEWER,
        ];
    }

    /** @return self[] */
    public static function forProviderCompany(): array
    {
        return [
            self::OWNER,
            self::OPERATIONS_MANAGER,
            self::DISPATCHER,
            self::TEAM_LEAD,
            self::QUALITY_MANAGER,
            self::FINANCE,
            self::WORKER,
            self::VIEWER,
        ];
    }

    // ──────────────────────────────────────────────────────
    // Hiérarchie : rang de l'autorité (plus haut = plus fort)
    // ──────────────────────────────────────────────────────
    public function rank(): int
    {
        return match ($this) {
            self::OWNER              => 100,
            self::OPERATIONS_MANAGER => 80,
            self::MANAGER            => 80,
            self::DISPATCHER         => 60,
            self::SITE_MANAGER       => 60,
            self::QUALITY_MANAGER    => 50,
            self::FINANCE            => 50,
            self::TEAM_LEAD          => 40,
            self::REQUESTER          => 20,
            self::WORKER             => 20,
            self::VIEWER             => 10,
        };
    }

    public function canManage(self $other): bool
    {
        return $this->rank() > $other->rank();
    }
}
