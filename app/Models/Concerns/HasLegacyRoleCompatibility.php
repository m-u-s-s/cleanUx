<?php

namespace App\Models\Concerns;

trait HasLegacyRoleCompatibility
{
    public function isAdmin(): bool
    {
        if (method_exists($this, 'isPlatformAdmin') && $this->isPlatformAdmin()) {
            return true;
        }

        return in_array($this->platform_role ?? null, ['admin', 'super_admin'], true)
            || ($this->role ?? null) === 'admin';
    }

    public function isClient(): bool
    {
        if (method_exists($this, 'isClientPersonal') && $this->isClientPersonal()) {
            return true;
        }

        if (method_exists($this, 'isClientCompany') && $this->isClientCompany()) {
            return true;
        }

        return ($this->role ?? null) === 'client';
    }

    public function isEmploye(): bool
    {
        if (method_exists($this, 'isProviderIndependent') && $this->isProviderIndependent()) {
            return true;
        }

        if (method_exists($this, 'isProviderCompanyWorker') && $this->isProviderCompanyWorker()) {
            return true;
        }

        return ($this->role ?? null) === 'employe';
    }

    public function isEntreprise(): bool
    {
        if (method_exists($this, 'isClientCompany') && $this->isClientCompany()) {
            return true;
        }

        if (method_exists($this, 'isProviderCompanyWorker') && $this->isProviderCompanyWorker()) {
            return true;
        }

        return ($this->role ?? null) === 'entreprise';
    }

    public function matchesRole(string $role): bool
    {
        return match ($role) {
            'admin' => $this->isAdmin(),
            'client' => $this->isClient(),
            'employe', 'employee', 'provider' => $this->isEmploye(),
            'entreprise', 'company' => $this->isEntreprise(),
            default => ($this->role ?? null) === $role,
        };
    }
}