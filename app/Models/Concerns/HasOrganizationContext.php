<?php

namespace App\Models\Concerns;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasOrganizationContext
{
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

    public function organizationContextId(): ?int
    {
        return $this->organization_account_id
            ?? $this->current_organization_id
            ?? data_get($this->metadata, 'organization_account_id')
            ?? data_get($this->metadata, 'entreprise_context.organization_account_id')
            ?? null;
    }

    public function organizationSites(): HasMany
    {
        return $this->hasMany(\App\Models\OrganizationSite::class, 'organization_account_id', 'organization_account_id');
    }
}
