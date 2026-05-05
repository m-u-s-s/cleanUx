<?php

namespace App\Models\Concerns;

trait HasLegacyRoleCompatibility
{
    public function isAdmin(): bool
    {
        return method_exists($this, 'isPlatformAdmin')
            ? $this->isPlatformAdmin()
            : in_array($this->platform_role ?? null, ['admin', 'super_admin'], true);
    }

    public function isClient(): bool
    {
        return $this->isClientPersonal() || $this->isClientCompany();
    }

    public function isEmploye(): bool
    {
        return $this->isProviderIndependent() || $this->isProviderCompanyWorker();
    }

    public function isEmployee(): bool
    {
        return $this->isEmploye();
    }

    public function isEntreprise(): bool
    {
        return $this->isClientCompany() || $this->isProviderCompanyWorker();
    }

    public function isEnterprise(): bool
    {
        return $this->isEntreprise();
    }

    public function isPremium(): bool
    {
        return (bool) ($this->customerProfile?->isPremium() ?? false);
    }

    public function matchesRole(string $role): bool
    {
        return match ($role) {
            'admin', 'platform_admin', 'super_admin' => $this->isAdmin(),
            'client', 'customer' => $this->isClient(),
            'employe', 'employee', 'provider' => $this->isEmploye(),
            'entreprise', 'enterprise', 'company' => $this->isEntreprise(),
            default => false,
        };
    }

    public function getRoleAttribute(): string
    {
        if ($this->isAdmin()) {
            return 'admin';
        }

        if ($this->isEmploye()) {
            return 'employe';
        }

        if ($this->isClient()) {
            return 'client';
        }

        return 'user';
    }
}
