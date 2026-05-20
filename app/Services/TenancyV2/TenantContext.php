<?php

namespace App\Services\TenancyV2;

use App\Models\Tenant;

/**
 * Holder du tenant courant pour la requête en cours.
 * Registered en singleton dans TenancyV2ServiceProvider — accessible via app(TenantContext::class).
 *
 * Utilisation typique :
 *  - middleware TenantResolver appelle set($tenant) en début de requête
 *  - BelongsToTenant trait appelle current() pour scope/auto-fill
 *  - reset() en CLI / queue worker entre 2 jobs
 */
class TenantContext
{
    protected ?Tenant $current = null;

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function set(?Tenant $tenant): void
    {
        $this->current = $tenant;
    }

    public function reset(): void
    {
        $this->current = null;
    }

    public function hasTenant(): bool
    {
        return $this->current !== null;
    }

    public function id(): ?int
    {
        return $this->current?->id;
    }

    public function code(): ?string
    {
        return $this->current?->code;
    }

    /**
     * Exécute un callable avec un tenant temporaire, puis restore.
     */
    public function runFor(Tenant $tenant, callable $fn)
    {
        $previous = $this->current;
        $this->current = $tenant;
        try {
            return $fn();
        } finally {
            $this->current = $previous;
        }
    }

    /**
     * Exécute sans tenant (mode platform-wide), puis restore.
     */
    public function runWithout(callable $fn)
    {
        $previous = $this->current;
        $this->current = null;
        try {
            return $fn();
        } finally {
            $this->current = $previous;
        }
    }
}
