<?php

namespace App\Models\Concerns;

use App\Enums\AssistantContextRole;
use App\Enums\CustomerType;
use App\Enums\ProviderType;

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

    public function isProvider(): bool
    {
        return $this->providerProfile()->exists();
    }

    public function isClientPersonal(): bool
    {
        $customerType = $this->customerProfile?->customer_type;

        if ($customerType instanceof CustomerType) {
            if ($customerType === CustomerType::PERSONAL) {
                return true;
            }
        } elseif ($customerType === CustomerType::PERSONAL->value) {
            return true;
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
            return true;
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

        if ($providerType instanceof ProviderType) {
            if ($providerType === ProviderType::INDEPENDENT) {
                return true;
            }
        } elseif ($providerType === ProviderType::INDEPENDENT->value) {
            return true;
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

        if ($providerType instanceof ProviderType) {
            return $providerType === ProviderType::COMPANY_WORKER;
        }

        return $providerType === ProviderType::COMPANY_WORKER->value;
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

        if ($this->isClientCompany()) {
            return AssistantContextRole::CLIENT_COMPANY;
        }

        if ($this->isClientPersonal()) {
            return AssistantContextRole::CLIENT_PERSONAL;
        }

        if ($this->isProviderCompanyWorker()) {
            return AssistantContextRole::PROVIDER_COMPANY;
        }

        if ($this->isProviderIndependent()) {
            return AssistantContextRole::PROVIDER_INDEPENDENT;
        }

        return AssistantContextRole::CLIENT_PERSONAL; // fallback
    }

    public function homeDashboardRoute(): string
    {
        if ($this->isPlatformAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isClientCompany()) {
            return 'client-company.dashboard';
        }

        if ($this->isClientPersonal()) {
            return 'client.dashboard';
        }

        if ($this->isProviderCompanyWorker()) {
            return 'provider-company.dashboard';
        }

        if ($this->isProviderIndependent()) {
            return 'employe.dashboard';
        }

        return 'dashboard';
    }

    /**
     * Unified role-string matcher used by CheckRole middleware.
     * No longer reads the legacy `role` column — uses typed fields only.
     */
    public function matchesRole(string $role): bool
    {
        return match ($role) {
            'admin', 'super_admin' => $this->isAdmin(),
            'client' => $this->isClient(),
            'employe', 'employee', 'provider' => $this->isEmploye(),
            'entreprise', 'company' => $this->isEntreprise(),
            default => ($this->platform_role ?? null) === $role,
        };
    }
}
