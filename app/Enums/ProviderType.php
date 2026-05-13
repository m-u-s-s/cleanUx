<?php

namespace App\Enums;

enum ProviderType: string
{
    case INDEPENDENT    = 'independent';    // Nettoyeur indépendant
    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';
    case COMPANY_WORKER = 'company_worker'; // Nettoyeur rattaché à une société
    

    public function label(): string
    {
        return match ($this) {
            self::INDEPENDENT    => 'Indépendant',
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
