<?php
// app/Enums/CustomerType.php
namespace App\Enums;

enum CustomerType: string
{
    case PERSONAL = 'personal'; // Particulier
    case COMPANY  = 'company';  // Entreprise cliente

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Particulier',
            self::COMPANY  => 'Entreprise',
        };
    }

    public function isCompany(): bool
    {
        return $this === self::COMPANY;
    }
}
