<?php

namespace App\Models\Concerns;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasOrganizationContext
{
    /** @return BelongsTo<OrganizationAccount, $this> */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'current_organization_id');
    }

    public function getOrganizationAccountIdAttribute(): ?int
    {
        return $this->attributes['organization_account_id']
            ?? $this->attributes['current_organization_id']
            ?? null;
    }

    /** @return HasMany<OrganizationMember, $this> */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function membershipIn(?OrganizationAccount $org = null): ?OrganizationMember
    {
        $orgId = $org?->id ?? $this->current_organization_id;

        if (! $orgId) {
            return null;
        }

        return $this->organizationMemberships()
            ->where('organization_account_id', $orgId)
            ->where('status', 'active')
            ->first();
    }

    public function roleIn(?OrganizationAccount $org = null): ?OrganizationRole
    {
        return $this->membershipIn($org)?->role;
    }

    public function canDoInOrg(string $permission, OrganizationAccount|int $org): bool
    {
        return app(PermissionService::class)->can($this, $permission, $org);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function hasOrganizationContext(): bool
    {
        return filled($this->organization_account_id)
            || filled($this->current_organization_id)
            || filled(data_get($this->metadata, 'organization_account_id'))
            || filled(data_get($this->metadata, 'entreprise_context'));
    }

    /**
     * L'utilisateur appartient-il à une entreprise CLIENTE active (espace société) ?
     * Condition du pont de navigation vers /dashboard/entreprise-client.
     */
    public function belongsToClientCompany(): bool
    {
        $type = OrganizationType::tryFrom(
            (string) $this->currentOrganization?->type
        );

        return $type?->isClient() ?? false;
    }

    public function organizationContextId(): ?int
    {
        return $this->organization_account_id
            ?? $this->current_organization_id
            ?? data_get($this->metadata, 'organization_account_id')
            ?? data_get($this->metadata, 'entreprise_context.organization_account_id')
            ?? null;
    }

    /** @return HasMany<OrganizationSite, $this> */
    public function organizationSites(): HasMany
    {
        return $this->hasMany(OrganizationSite::class, 'organization_account_id', 'organization_account_id');
    }
}
