<?php

namespace App\Enums;

enum ProviderType: string
{
    case INDEPENDENT = 'independent';    // Nettoyeur indépendant
    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';
    case COMPANY_WORKER = 'company_worker'; // Nettoyeur rattaché à une société

    /** Les quatre cas sont couverts : un `match` sans branche par défaut lève une UnhandledMatchError, et INDIVIDUAL comme COMPANY n'en avaient aucune. */
    public function label(): string
    {
        return match ($this) {
            self::INDEPENDENT => 'Indépendant',
            self::INDIVIDUAL => 'Particulier',
            self::COMPANY => 'Société',
            self::COMPANY_WORKER => 'Employé en société',
        };
    }

    /** DEUX VALEURS PAR CAMP, ET L'ÉNUMÉRATION EST LA SEULE À LE SAVOIR. */
    public function isIndependent(): bool
    {
        return $this === self::INDEPENDENT || $this === self::INDIVIDUAL;
    }

    public function isCompanyWorker(): bool
    {
        return $this === self::COMPANY_WORKER || $this === self::COMPANY;
    }

    /**
     * Les valeurs à interroger en base pour un camp donné.
     *
     * @return list<string>
     */
    public static function valeursIndependantes(): array
    {
        return [self::INDEPENDENT->value, self::INDIVIDUAL->value];
    }

    /** @return list<string> */
    public static function valeursDeSociete(): array
    {
        return [self::COMPANY_WORKER->value, self::COMPANY->value];
    }

    /** @return list<string> */
    public static function toutesLesValeurs(): array
    {
        return array_merge(self::valeursIndependantes(), self::valeursDeSociete());
    }
}
