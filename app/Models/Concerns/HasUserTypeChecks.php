<?php

namespace App\Models\Concerns;

use App\Enums\AssistantContextRole;
use App\Enums\CustomerType;
use App\Enums\ProviderType;

trait HasUserTypeChecks
{
    public const ROLE_CLIENT    = 'client';
    public const ROLE_EMPLOYE   = 'employe';
    public const ROLE_EMPLOYEE  = 'employe';
    public const ROLE_ENTREPRISE = 'entreprise';
    public const ROLE_PROVIDER  = 'provider';

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
        return $this->customerProfile?->customer_type === CustomerType::PERSONAL->value;
    }

    public function isClientCompany(): bool
    {
        if ($this->customerProfile?->customer_type === CustomerType::COMPANY->value) {
            return true;
        }

        return ! empty($this->organization_account_id);
    }

    public function isProviderIndependent(): bool
    {
        return $this->providerProfile?->provider_type === ProviderType::INDEPENDENT->value;
    }

    public function isProviderCompanyWorker(): bool
    {
        return $this->providerProfile?->provider_type === ProviderType::COMPANY_WORKER->value;
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
