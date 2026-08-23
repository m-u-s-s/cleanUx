<?php

namespace App\Models\Concerns;

use App\Enums\AssistantContextRole;
use App\Enums\CustomerType;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Enums\Role;
use App\Http\Middleware\CheckRole;
use App\Models\OrganizationAccount;
use Illuminate\Database\Eloquent\Builder;

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

    /** Broad "is this user a provider at all?" check — true as soon as a provider_profile row exists, regardless of provider_type. */
    public function isProvider(): bool
    {
        return $this->providerProfile()->exists();
    }

    /**
     * INTERROGER UNE POPULATION, AVEC LA MÊME RÈGLE QUE POUR UN COMPTE.
     *
     * @param  Builder<static>  $query
     */
    public function scopeProviders(Builder $query): void
    {
        // Miroir de `isEmploye()` = `isProviderIndependent() || isProviderCompanyWorker()`.
        // Un profil renseigné suffit, quel que soit son type — les quatre valeurs de l'énumération
        // désignent un prestataire. Sans profil, la colonne héritée décide.
        $query->where(function (Builder $q) {
            $q->whereHas('providerProfile', fn ($p) => $p->whereNotNull('provider_type'))
                ->orWhere(function (Builder $sans) {
                    $sans->whereDoesntHave('providerProfile', fn ($p) => $p->whereNotNull('provider_type'))
                        ->where('role', self::ROLE_EMPLOYE);
                });
        });
    }

    /**
     * Miroir de `isAdmin()`.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAdmins(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereIn('platform_role', [self::ROLE_ADMIN, 'super_admin'])
                ->orWhereIn('role', [self::ROLE_ADMIN, 'super_admin']);
        });
    }

    /**
     * Miroir de `isClientCompany()` — les deux signaux typés, puis l'hérité s'ils se taisent.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCompanyClients(Builder $query): void
    {
        $typesClients = array_values(array_map(
            fn (OrganizationType $t) => $t->value,
            array_filter(OrganizationType::cases(), fn (OrganizationType $t) => $t->isClient()),
        ));

        $query->where(function (Builder $q) use ($typesClients) {
            $q->whereHas('customerProfile', fn ($p) => $p->where('customer_type', CustomerType::COMPANY->value))
                ->orWhereHas('organizationAccount', fn ($o) => $o->whereIn('type', $typesClients))
                ->orWhere(function (Builder $sans) {
                    $sans->whereDoesntHave('customerProfile', fn ($p) => $p->whereNotNull('customer_type'))
                        ->whereNull('organization_account_id')
                        ->where('role', self::ROLE_ENTREPRISE);
                });
        });
    }

    /**
     * Miroir de `isClient()` = particuliers ET sociétés.
     *
     * @param  Builder<static>  $query
     */
    public function scopeClients(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereHas('customerProfile', fn ($p) => $p->whereNotNull('customer_type'))
                ->orWhere(fn (Builder $societe) => $societe->companyClients())
                ->orWhere(function (Builder $sans) {
                    $sans->whereDoesntHave('customerProfile', fn ($p) => $p->whereNotNull('customer_type'))
                        ->whereIn('role', [self::ROLE_CLIENT, self::ROLE_ENTREPRISE]);
                });
        });
    }

    /** UN REPLI NE PARLE QUE QUAND L'AUTRE SE TAIT. */
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
         * have a populated customer_profile row.
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

        // LES DEUX SIGNAUX TYPÉS RESTENT GÉNÉREUX, SEUL L'HÉRITÉ SE TAIT.
        if ($customerType !== null || ! empty($this->organization_account_id)) {
            return false;
        }

        /**
         * @deprecated Reading the legacy `role` column directly. Remove once all users
         * have a populated customer_profile row.
         */
        // Legacy fallback: role column still populated during transition
        return ($this->attributes['role'] ?? $this->role ?? null) === 'entreprise';
    }

    public function isProviderIndependent(): bool
    {
        $providerType = $this->providerProfile?->provider_type;

        // `provider_type` est CASTÉ en énumération sur le modèle : cette valeur est donc toujours un `ProviderType` ou nul, jamais une chaîne.
        // `isIndependent()` PLUTÔT QU'UNE COMPARAISON DIRECTE.
        if ($providerType !== null) {
            // UN PROFIL RENSEIGNÉ TRANCHE, DANS LES DEUX SENS.
            return $providerType->isIndependent();
        }

        /**
         * @deprecated Reading the legacy `role` column directly. Remove once all users
         * have a populated provider_profile row.
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

    /** Single source of truth for the role-string matching used by the `role:` route middleware ({@see CheckRole}). */
    public function matchesRole(string $role): bool
    {
        return match ($role) {
            'admin' => $this->isAdmin(),
            'super_admin' => $this->roleCanonique() === Role::SUPER_ADMIN,
            'client' => $this->isClient(),
            'employe', 'employee', 'provider' => $this->isEmploye(),
            'entreprise', 'company' => $this->isEntreprise(),
            'provider_company', 'entreprise_prestataire' => $this->isProviderCompanyWorker(),

            // LES SIX RÔLES CANONIQUES.
            Role::SUPER_ADMIN->value.'_canonique' => $this->roleCanonique() === Role::SUPER_ADMIN,
            'client_individuelle' => $this->roleCanonique() === Role::CLIENT_INDIVIDUELLE,
            'client_societe' => $this->roleCanonique() === Role::CLIENT_SOCIETE,
            'provider_individuelle' => $this->roleCanonique() === Role::PROVIDER_INDIVIDUELLE,
            'provider_societe' => $this->roleCanonique() === Role::PROVIDER_SOCIETE,

            default => ($this->platform_role ?? null) === $role,
        };
    }

    /** LE RÔLE DU COMPTE, TRANCHÉ UNE FOIS. */
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

        // Le repli n'est pas un aveu d'échec : un compte tout juste créé n'a encore ni profil client ni profil prestataire.
        return Role::CLIENT_INDIVIDUELLE;
    }
}
