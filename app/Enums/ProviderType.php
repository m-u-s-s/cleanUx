<?php

namespace App\Enums;

enum ProviderType: string
{
    case INDEPENDENT = 'independent';    // Nettoyeur indépendant
    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';
    case COMPANY_WORKER = 'company_worker'; // Nettoyeur rattaché à une société

    /**
     * Les quatre cas sont couverts : un `match` sans branche par défaut lève une
     * UnhandledMatchError, et INDIVIDUAL comme COMPANY n'en avaient aucune. L'inscription en
     * libre-service crée désormais des profils COMPANY, ce qui rend ce chemin atteignable.
     */
    public function label(): string
    {
        return match ($this) {
            self::INDEPENDENT => 'Indépendant',
            self::INDIVIDUAL => 'Particulier',
            self::COMPANY => 'Société',
            self::COMPANY_WORKER => 'Employé en société',
        };
    }

    public function isIndependent(): bool
    {
        return $this === self::INDEPENDENT;
    }

    public function isCompanyWorker(): bool
    {
        return $this === self::COMPANY_WORKER;
    }
}
