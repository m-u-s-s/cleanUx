<?php

namespace App\Models\Concerns;

/**
 * Kept for the `matchesRole()` contract used by CheckRole middleware.
 * All references to the legacy `role` column have been removed following
 * migration A3 (DROP COLUMN role on users table).
 */
trait HasLegacyRoleCompatibility
{
    public function matchesRole(string $role): bool
    {
        return match ($role) {
            'admin', 'super_admin' => $this->isAdmin(),
            'client' => $this->isClient(),
            'employe', 'employee', 'provider' => $this->isEmploye(),
            'entreprise', 'company' => $this->isEntreprise(),
            'provider_company', 'entreprise_prestataire' => $this->isProviderCompanyWorker(),
            default => ($this->platform_role ?? null) === $role,
        };
    }
}
