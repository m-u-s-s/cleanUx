<?php

namespace App\Enums;

/**
 * LES SIX RÔLES DE LA PLATEFORME — la source unique.
 *
 * POURQUOI CETTE ÉNUMÉRATION EXISTE. Le rôle d'un compte se déduisait de cinq signaux répartis :
 * `platform_role`, la colonne `role` (héritée), `customer_type`, `provider_type` et le type de
 * l'organisation courante. 217 appels dans 65 fichiers les interrogeaient séparément, chacun avec
 * sa propre idée de l'ordre de priorité — c'est ainsi que la navbar a servi le menu client à un
 * administrateur pendant toute une livraison.
 *
 * Les prédicats `is*()` du modèle restent : ils deviennent l'IMPLÉMENTATION de la résolution, plus
 * la décision. Rien ne les remplace en bloc — 217 réécritures en une fois seraient un changement
 * qu'aucune revue ne peut lire.
 *
 * L'ORDRE DES CAS EST L'ORDRE DE PRIORITÉ, et il n'est pas cosmétique : un compte satisfait souvent
 * plusieurs signaux à la fois — un administrateur reste client, un gérant intervient sur le
 * terrain. `RoleCanoniqueTest` fige cet ordre cas par cas.
 */
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

    /**
     * La route d'atterrissage après connexion.
     *
     * `admin`, `client_individuelle` et `provider_individuelle` gardent celui qu'ils ont déjà —
     * c'est une consigne explicite, et leurs tableaux de bord sont écrits, testés et utilisés.
     * `client_societe` et `provider_societe` ont les leurs depuis les espaces société.
     * `super_admin` est le seul qui en gagne un.
     */
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

    /**
     * Le contexte de `config/modules.php` — la page Modules et la navbar s'y accrochent.
     *
     * Le super administrateur emprunte celui de l'administration : ses 83 modules sont les mêmes,
     * son tableau de bord ajoute une couche au-dessus plutôt qu'il ne remplace la console.
     */
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

    /**
     * SEULE LA SOCIÉTÉ PRESTATAIRE PORTE DES SOUS-RÔLES.
     *
     * Les onze rôles d'organisation décrivent le travail DANS une société qui exécute : qui
     * répartit, qui mène une équipe terrain, qui répond de la qualité. Un particulier — client ou
     * prestataire — n'a personne à qui déléguer, et un administrateur relève de `platform_role`.
     */
    public function porteDesSousRoles(): bool
    {
        return $this === self::PROVIDER_SOCIETE;
    }

    /**
     * La liste vient de `OrganizationRole::forProviderCompany()`, pas de `cases()` : deux listes
     * des mêmes sous-rôles finiraient par diverger, et c'est celle-là que les écrans d'invitation
     * proposent réellement.
     *
     * @return list<OrganizationRole>
     */
    public function sousRoles(): array
    {
        return $this->porteDesSousRoles() ? OrganizationRole::forProviderCompany() : [];
    }
}
