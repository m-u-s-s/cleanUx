<?php

namespace App\Enums;

/** LES SIX RÔLES DE LA PLATEFORME — la source unique. POURQUOI CETTE ÉNUMÉRATION EXISTE. */
enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case CLIENT_INDIVIDUELLE = 'client_individuelle';
    case CLIENT_SOCIETE = 'client_societe';
    case PROVIDER_INDIVIDUELLE = 'provider_individuelle';
    case PROVIDER_SOCIETE = 'provider_societe';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super administrateur',
            self::ADMIN => 'Administrateur',
            self::CLIENT_INDIVIDUELLE => 'Client particulier',
            self::CLIENT_SOCIETE => 'Client société',
            self::PROVIDER_INDIVIDUELLE => 'Prestataire indépendant',
            self::PROVIDER_SOCIETE => 'Société prestataire',
        };
    }

    /** La route d'atterrissage après connexion. */
    public function routeDuTableauDeBord(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'super-admin.dashboard',
            self::ADMIN => 'admin.dashboard',
            self::CLIENT_INDIVIDUELLE => 'client.dashboard',
            self::CLIENT_SOCIETE => 'client-company.dashboard',
            self::PROVIDER_INDIVIDUELLE => 'employe.dashboard',
            self::PROVIDER_SOCIETE => 'provider-company.dashboard',
        };
    }

    /** Le contexte de `config/modules.php` — la page Modules et la navbar s'y accrochent. */
    public function contexteDeModules(): string
    {
        return match ($this) {
            self::SUPER_ADMIN, self::ADMIN => 'admin',
            self::CLIENT_INDIVIDUELLE => 'client',
            self::CLIENT_SOCIETE => 'client-company',
            self::PROVIDER_INDIVIDUELLE => 'employe',
            self::PROVIDER_SOCIETE => 'provider-company',
        };
    }

    /** SEULE LA SOCIÉTÉ PRESTATAIRE PORTE DES SOUS-RÔLES. */
    public function porteDesSousRoles(): bool
    {
        return $this === self::PROVIDER_SOCIETE;
    }

    /**
     * La liste vient de `OrganizationRole::forProviderCompany()`, pas de `cases()` : deux listes des mêmes sous-rôles finiraient par diverger, et c'est celle-là que les écrans d'invitation proposent réellement.
     *
     * @return list<OrganizationRole>
     */
    public function sousRoles(): array
    {
        return $this->porteDesSousRoles() ? OrganizationRole::forProviderCompany() : [];
    }
}
