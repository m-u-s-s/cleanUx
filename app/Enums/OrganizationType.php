<?php

namespace App\Enums;

enum OrganizationType: string
{
    case CLIENT_COMPANY   = 'client_company';   // Entreprise qui demande le service
    case PROVIDER_COMPANY = 'provider_company'; // Entreprise qui fournit le service
    case PROVIDER_SOLO    = 'provider_solo';    // Indépendant avec structure légale
    case HYBRID           = 'hybrid';           // Les deux à la fois (rare)

    public function label(): string
    {
        return match ($this) {
            self::CLIENT_COMPANY   => 'Entreprise cliente',
            self::PROVIDER_COMPANY => 'Société de nettoyage',
            self::PROVIDER_SOLO    => 'Indépendant',
            self::HYBRID           => 'Hybride',
        };
    }

    public function isClient(): bool
    {
        return in_array($this, [self::CLIENT_COMPANY, self::HYBRID], true);
    }

    public function isProvider(): bool
    {
        return in_array($this, [self::PROVIDER_COMPANY, self::PROVIDER_SOLO, self::HYBRID], true);
    }

    /** @return OrganizationRole[] */
    public function availableRoles(): array
    {
        return match ($this) {
            self::CLIENT_COMPANY   => OrganizationRole::forClientCompany(),
            self::PROVIDER_COMPANY,
            self::PROVIDER_SOLO    => OrganizationRole::forProviderCompany(),
            self::HYBRID           => array_merge(
                OrganizationRole::forClientCompany(),
                OrganizationRole::forProviderCompany()
            ),
        };
    }
}
