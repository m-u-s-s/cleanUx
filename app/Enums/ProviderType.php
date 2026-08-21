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

    /**
     * DEUX VALEURS PAR CAMP, ET L'ÉNUMÉRATION EST LA SEULE À LE SAVOIR.
     *
     * `INDEPENDENT` et `INDIVIDUAL` désignent la même chose — un prestataire qui travaille
     * seul ; `COMPANY` et `COMPANY_WORKER` désignent l'appartenance à une société. Deux
     * chemins d'inscription coexistent et ne produisent pas la même valeur :
     * `ApiAuthController` écrit `INDEPENDENT`, `ProviderOnboardingService` écrivait
     * `individual`.
     *
     * Le moteur de répartition le savait déjà et acceptait les deux ; le modèle, lui,
     * comparait à la seule valeur canonique. Un prestataire inscrit par le second chemin
     * était donc CANDIDAT AUX MISSIONS tout en étant refusé de son propre espace.
     *
     * La règle vit désormais ici, à un seul endroit. Les appelants la lisent, ils ne la
     * recopient plus.
     */
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
     * Les requêtes ont besoin de chaînes, pas d'objets : cette liste évite qu'un service
     * réécrive `['independent', 'individual']` de son côté — ce qui divergerait à la
     * première valeur ajoutée.
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
