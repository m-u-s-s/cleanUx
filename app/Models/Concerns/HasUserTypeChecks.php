<?php

namespace App\Models\Concerns;

use App\Enums\AssistantContextRole;
use App\Enums\CustomerType;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Enums\Role;
use App\Http\Middleware\CheckRole;
use App\Models\OrganizationAccount;

trait HasUserTypeChecks
{
    public const ROLE_CLIENT = 'client';

    public const ROLE_EMPLOYE = 'employe';

    public const ROLE_EMPLOYEE = 'employe';

    public const ROLE_ENTREPRISE = 'entreprise';

    public const ROLE_PROVIDER = 'provider';

    public function isCustomer(): bool
    {
        return $this->customerProfile()->exists();
    }

    public function isStandard(): bool
    {
        return $this->plan_type === 'standard';
    }

    /**
     * Broad "is this user a provider at all?" check — true as soon as a
     * provider_profile row exists, regardless of provider_type.
     *
     * NOTE — this is NOT the check used by the `role:provider` / `role:employe`
     * route middleware. That middleware resolves through {@see matchesRole()},
     * which maps both `provider` and `employe` to {@see User::isEmploye()}
     * (independent OR company-worker). Use isProvider() for "has a provider
     * profile" gating; use isEmploye() / role:provider for route-level access.
     */
    public function isProvider(): bool
    {
        return $this->providerProfile()->exists();
    }

    /**
     * UN REPLI NE PARLE QUE QUAND L'AUTRE SE TAIT.
     *
     * L'ancienne version testait « le type est-il PERSONAL ? », et retombait sur la colonne
     * héritée dans TOUS les autres cas — y compris quand le profil disait clairement autre chose.
     * Un client dont `customer_type` vaut `company` et dont `role` vaut `client` répondait donc
     * OUI à « êtes-vous un particulier ? ».
     *
     * Mesuré sur `brio` avant correction : DIX clients société sur trente comptes répondaient oui.
     * Aucun n'en souffrait — tous les appelants testent la société AVANT le particulier, si bien
     * que la mauvaise réponse n'était jamais atteinte. C'est un défaut latent, pas une panne : il
     * attend le premier appelant qui interrogera ce prédicat seul.
     *
     * La règle est désormais celle que le commentaire d'origine décrivait déjà — « remove once all
     * users have a populated customer_profile row » : un profil renseigné TRANCHE, et la colonne
     * héritée ne parle que s'il n'y en a pas.
     */
    public function isClientPersonal(): bool
    {
        $customerType = $this->customerProfile?->customer_type;

        if ($customerType !== null) {
            return $customerType instanceof CustomerType
                ? $customerType === CustomerType::PERSONAL
                : $customerType === CustomerType::PERSONAL->value;
        }

        /**
         * @deprecated Reading the legacy `role` column directly. Remove once all users
         *             have a populated customer_profile row.
         */
        // Legacy fallback: role column still populated during transition
        return ($this->attributes['role'] ?? $this->role ?? null) === 'client';
    }

    public function isClientCompany(): bool
    {
        $customerType = $this->customerProfile?->customer_type;

        if ($customerType instanceof CustomerType) {
            if ($customerType === CustomerType::COMPANY) {
                return true;
            }
        } elseif ($customerType === CustomerType::COMPANY->value) {
            return true;
        }

        if (! empty($this->organization_account_id)) {
            // `organization_accounts.type` is an uncast string column; resolve
            // it via a typed query so the value type is known (not mixed).
            $orgType = OrganizationAccount::query()
                ->whereKey($this->organization_account_id)
                ->value('type');
            $enum = OrganizationType::tryFrom((string) $orgType);
            if ($enum !== null) {
                // CLIENT_COMPANY / HYBRID → cliente ; PROVIDER_* → non.
                return $enum->isClient();
            }
            // type inconnu : on retombe sur le fallback legacy ci-dessous.
        }

        /*
         * LES DEUX SIGNAUX TYPÉS RESTENT GÉNÉREUX, SEUL L'HÉRITÉ SE TAIT.
         *
         * On ne fait PAS trancher `customer_type` seul ici, contrairement à `isClientPersonal()`.
         * Un particulier peut appartenir à une société cliente — c'est le cas C2B que cette
         * plateforme sert — et lui refuser l'espace société sur la foi de son profil personnel le
         * mettrait dehors d'un espace où il a sa place. Les deux signaux valent donc toujours.
         *
         * Ce qui change : la colonne héritée ne parle plus que si AUCUN des deux ne s'est exprimé.
         * Sans cette condition, un profil disant « particulier » et un `role` disant « entreprise »
         * ouvraient quand même les quatorze écrans gardés par `abort_unless(isClientCompany())`.
         * Mesuré avant correction : zéro compte dans ce cas — le trou existait sans être emprunté.
         */
        if ($customerType !== null || ! empty($this->organization_account_id)) {
            return false;
        }

        /**
         * @deprecated Reading the legacy `role` column directly. Remove once all users
         *             have a populated customer_profile row.
         */
        // Legacy fallback: role column still populated during transition
        return ($this->attributes['role'] ?? $this->role ?? null) === 'entreprise';
    }

    public function isProviderIndependent(): bool
    {
        $providerType = $this->providerProfile?->provider_type;

        /*
         * `provider_type` est CASTÉ en énumération sur le modèle : cette valeur est donc toujours
         * un `ProviderType` ou nul, jamais une chaîne. La branche qui comparait à `->value` était
         * morte depuis que le cast existe, et l'analyse statique ne pouvait pas le dire tant que
         * l'annotation de la relation désignait le mauvais modèle.
         */
        /*
            `isIndependent()` PLUTÔT QU'UNE COMPARAISON DIRECTE.

            `INDEPENDENT` et `INDIVIDUAL` désignent le même prestataire, et deux chemins
            d'inscription produisent l'une ou l'autre. Comparer à la seule valeur canonique
            refusait de son espace un prestataire que le dispatch envoyait pourtant en mission.
            L'énumération porte la règle ; on la lui demande.
        */
        if ($providerType !== null) {
            /*
             * UN PROFIL RENSEIGNÉ TRANCHE, DANS LES DEUX SENS.
             *
             * L'ancienne version ne rendait `true` que sur un type indépendant, puis retombait sur
             * la colonne héritée — si bien qu'un prestataire SALARIÉ dont `role` valait `employe`
             * répondait oui à « êtes-vous indépendant ? ». Mesuré sur `brio` : NEUF prestataires
             * `company_worker` sur trente comptes.
             *
             * Sans conséquence à ce jour : le seul appelant est `isEmploye()`, qui unit ce
             * prédicat à `isProviderCompanyWorker()` par un `||` — les deux vrais donnent la même
             * réponse. Le défaut attendait le premier appelant qui l'interrogerait seul, pour
             * décider d'un versement ou d'un rattachement.
             */
            return $providerType->isIndependent();
        }

        /**
         * @deprecated Reading the legacy `role` column directly. Remove once all users
         *             have a populated provider_profile row.
         */
        // Legacy fallback: role column still populated during transition
        return ($this->attributes['role'] ?? $this->role ?? null) === 'employe';
    }

    public function isProviderCompanyWorker(): bool
    {
        $providerType = $this->providerProfile?->provider_type;

        // Même raison : le cast garantit l'énumération. Un profil absent rend nul, donc faux — ce
        // qui est le comportement voulu, un compte sans profil n'étant pas salarié d'une société.
        //
        // `COMPANY` et `COMPANY_WORKER` valent tous deux « rattaché à une société » : la même
        // divergence que du côté indépendant, et le même remède — c'est l'énumération qui tranche.
        return (bool) $providerType?->isCompanyWorker();
    }

    public function getIsEmployeAttribute(): bool
    {
        return $this->isEmploye();
    }

    public function getIsClientAttribute(): bool
    {
        return $this->isClient();
    }

    public function getIsEntrepriseAttribute(): bool
    {
        return $this->isEntreprise();
    }

    public function assistantContextRole(): AssistantContextRole
    {
        if ($this->isPlatformAdmin()) {
            return AssistantContextRole::ADMIN;
        }

        if ($this->isProviderCompanyWorker()) {
            return AssistantContextRole::PROVIDER_COMPANY;
        }

        if ($this->isProviderIndependent()) {
            return AssistantContextRole::PROVIDER_INDEPENDENT;
        }

        if ($this->isClientCompany()) {
            return AssistantContextRole::CLIENT_COMPANY;
        }

        if ($this->isClientPersonal()) {
            return AssistantContextRole::CLIENT_PERSONAL;
        }

        return AssistantContextRole::CLIENT_PERSONAL; // fallback
    }

    public function homeDashboardRoute(): string
    {
        if ($this->isPlatformAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isProviderCompanyWorker()) {
            return 'provider-company.dashboard';
        }

        if ($this->isProviderIndependent()) {
            return 'employe.dashboard';
        }

        if ($this->isClientCompany()) {
            return 'client-company.dashboard';
        }

        if ($this->isClientPersonal()) {
            return 'client.dashboard';
        }

        return 'dashboard';
    }

    /**
     * Single source of truth for the role-string matching used by the
     * `role:` route middleware ({@see CheckRole}).
     * Uses typed fields only (no legacy `role` column — dropped in migration A3).
     *
     * Provider aliases (all three resolve to the SAME check, isEmploye):
     *   - `employe`  / `employee` / `provider` → independent OR company worker
     *   - `provider_company` / `entreprise_prestataire` → company worker only
     * Keep `role:employe` as the canonical token in routes; `role:provider`
     * is an accepted synonym so future code reads naturally either way.
     */
    public function matchesRole(string $role): bool
    {
        return match ($role) {
            'admin' => $this->isAdmin(),
            'super_admin' => $this->roleCanonique() === Role::SUPER_ADMIN,
            'client' => $this->isClient(),
            'employe', 'employee', 'provider' => $this->isEmploye(),
            'entreprise', 'company' => $this->isEntreprise(),
            'provider_company', 'entreprise_prestataire' => $this->isProviderCompanyWorker(),

            /*
             * LES SIX RÔLES CANONIQUES.
             *
             * Ils se comparent au rôle RÉSOLU, pas à un signal isolé : `client_individuelle` doit
             * être faux pour un administrateur qui garde un profil client, ce qu'un simple
             * `isClientPersonal()` ne saurait pas dire.
             */
            Role::SUPER_ADMIN->value.'_canonique' => $this->roleCanonique() === Role::SUPER_ADMIN,
            'client_individuelle' => $this->roleCanonique() === Role::CLIENT_INDIVIDUELLE,
            'client_societe' => $this->roleCanonique() === Role::CLIENT_SOCIETE,
            'provider_individuelle' => $this->roleCanonique() === Role::PROVIDER_INDIVIDUELLE,
            'provider_societe' => $this->roleCanonique() === Role::PROVIDER_SOCIETE,

            default => ($this->platform_role ?? null) === $role,
        };
    }

    /**
     * LE RÔLE DU COMPTE, TRANCHÉ UNE FOIS.
     *
     * L'ordre des tests EST la règle, et chaque marche est là pour un défaut déjà vu :
     *
     * 1. `super_admin` avant `admin` — sinon le premier n'existerait jamais, `isAdmin()` étant vrai
     *    pour les deux.
     * 2. L'administration avant tout le reste — promouvoir un client en administrateur ne lui
     *    retire pas son profil client, et tester le client d'abord rendait la promotion invisible.
     * 3. La SOCIÉTÉ avant l'individuel, des deux côtés — un membre de société coche aussi les
     *    signaux du particulier, jamais l'inverse. Tester l'individuel d'abord enfermerait tout le
     *    monde dans l'espace perso.
     * 4. Le client avant le prestataire, à défaut de mieux : un compte qui serait les deux relève
     *    d'un choix d'espace, pas d'une résolution automatique — voir les sélecteurs des deux
     *    applications mobiles.
     */
    public function roleCanonique(): Role
    {
        if (($this->platform_role ?? null) === 'super_admin') {
            return Role::SUPER_ADMIN;
        }

        if ($this->isAdmin()) {
            return Role::ADMIN;
        }

        if ($this->isClientCompany()) {
            return Role::CLIENT_SOCIETE;
        }

        if ($this->isProviderCompanyWorker()) {
            return Role::PROVIDER_SOCIETE;
        }

        if ($this->isClientPersonal()) {
            return Role::CLIENT_INDIVIDUELLE;
        }

        if ($this->isEmploye()) {
            return Role::PROVIDER_INDIVIDUELLE;
        }

        /*
         * Le repli n'est pas un aveu d'échec : un compte tout juste créé n'a encore ni profil
         * client ni profil prestataire. `client_individuelle` est ce qu'il est dans les faits —
         * c'est aussi le défaut de la colonne `role` en base.
         */
        return Role::CLIENT_INDIVIDUELLE;
    }
}
