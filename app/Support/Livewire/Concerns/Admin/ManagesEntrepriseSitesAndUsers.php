<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ManagesEntrepriseSitesAndUsers
{
    public function editSite(int $siteId): void
    {
        $site = OrganizationSite::findOrFail($siteId);
        $this->siteId = $site->id;
        $this->site_name = (string) $site->name;
        $this->site_code = (string) ($site->site_code ?? '');
        $this->site_contact_name = (string) ($site->contact_name ?? '');
        $this->site_email = (string) ($site->email ?? '');
        $this->site_phone = (string) ($site->phone ?? '');
        $this->site_address_line_1 = (string) ($site->address_line_1 ?? '');
        $this->site_address_line_2 = (string) ($site->address_line_2 ?? '');
        $this->site_city = (string) ($site->city ?? '');
        $this->site_postal_code = (string) ($site->postal_code ?? '');
        $this->site_access_instructions = (string) ($site->access_instructions ?? '');
        $this->site_zone_id = (string) ($site->service_zone_id ?? '');
        $this->site_approval_mode = (string) data_get($site->metadata, 'approval_mode', 'inherit');
        $this->site_purchase_order_required = (bool) data_get($site->metadata, 'purchase_order_required', false);
        $this->site_default_cost_center = (string) data_get($site->metadata, 'default_cost_center', '');
        $this->site_priority_level = (string) data_get($site->metadata, 'priority_level', '');
        $this->site_requires_manual_validation = (bool) data_get($site->metadata, 'requires_manual_validation', false);
        $this->site_tags = collect((array) data_get($site->metadata, 'site_tags', []))->implode(', ');
        $this->site_is_primary = (bool) $site->is_primary;
        $this->site_is_active = (bool) $site->is_active;
    }

    public function resetSiteForm(): void
    {
        $this->reset([
            'siteId', 'site_name', 'site_code', 'site_contact_name', 'site_email', 'site_phone',
            'site_address_line_1', 'site_address_line_2', 'site_city', 'site_postal_code',
            'site_access_instructions', 'site_zone_id', 'site_approval_mode', 'site_purchase_order_required',
            'site_default_cost_center', 'site_priority_level', 'site_requires_manual_validation', 'site_tags',
            'site_is_primary', 'site_is_active',
        ]);

        $this->site_approval_mode = 'inherit';
        $this->site_is_active = true;
        $this->site_is_primary = false;
    }

    public function saveSite(): void
    {
        if (! $this->selectedAccountId) {
            return;
        }

        $validated = $this->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'site_code' => ['nullable', 'string', 'max:50'],
            'site_contact_name' => ['nullable', 'string', 'max:255'],
            'site_email' => ['nullable', 'email', 'max:255'],
            'site_phone' => ['nullable', 'string', 'max:30'],
            'site_address_line_1' => ['nullable', 'string', 'max:255'],
            'site_address_line_2' => ['nullable', 'string', 'max:255'],
            'site_city' => ['nullable', 'string', 'max:255'],
            'site_postal_code' => ['nullable', 'string', 'max:20'],
            'site_access_instructions' => ['nullable', 'string'],
            'site_zone_id' => ['nullable', 'integer', Rule::exists('service_zones', 'id')],
            'site_approval_mode' => ['required', Rule::in(['inherit', 'auto', 'client', 'admin'])],
            'site_purchase_order_required' => ['boolean'],
            'site_default_cost_center' => ['nullable', 'string', 'max:100'],
            'site_priority_level' => ['nullable', 'string', 'max:50'],
            'site_requires_manual_validation' => ['boolean'],
            'site_tags' => ['nullable', 'string'],
        ]);

        $postalCode = $this->resolvePostalCodeReference($validated['site_postal_code'] ?? null);
        $zoneId = $validated['site_zone_id'] !== '' ? (int) $validated['site_zone_id'] : $this->resolveZoneIdFromPostalCode($postalCode);

        if ($this->site_is_primary) {
            OrganizationSite::where('organization_account_id', $this->selectedAccountId)->update(['is_primary' => false]);
        }

        $site = OrganizationSite::updateOrCreate(
            ['id' => $this->siteId],
            [
                'organization_account_id' => $this->selectedAccountId,
                'service_zone_id' => $zoneId,
                'postal_code_id' => $postalCode?->id,
                'name' => $validated['site_name'],
                'site_code' => $validated['site_code'] ?: null,
                'contact_name' => $validated['site_contact_name'] ?: null,
                'email' => $validated['site_email'] ?: null,
                'phone' => $validated['site_phone'] ?: null,
                'address_line_1' => $validated['site_address_line_1'] ?: null,
                'address_line_2' => $validated['site_address_line_2'] ?: null,
                'city' => $validated['site_city'] ?: ($postalCode?->city_name ?: null),
                'postal_code' => $validated['site_postal_code'] ?: null,
                'access_instructions' => $validated['site_access_instructions'] ?: null,
                'is_primary' => $this->site_is_primary,
                'is_active' => $this->site_is_active,
                'metadata' => [
                    'approval_mode' => $validated['site_approval_mode'],
                    'purchase_order_required' => (bool) ($validated['site_purchase_order_required'] ?? false),
                    'default_cost_center' => $validated['site_default_cost_center'] ?: null,
                    'priority_level' => $validated['site_priority_level'] ?: null,
                    'requires_manual_validation' => (bool) ($validated['site_requires_manual_validation'] ?? false),
                    'site_tags' => collect(explode(',', (string) ($validated['site_tags'] ?? '')))->map(fn ($tag) => trim($tag))->filter()->values()->all(),
                ],
            ]
        );

        ActivityLogger::log($this->siteId ? 'organization_site.updated' : 'organization_site.created', $site, [
            'organization_account_id' => $this->selectedAccountId,
            'service_zone_id' => $zoneId,
        ]);

        $this->resetSiteForm();
        session()->flash('success', 'Site entreprise enregistré.');
    }

    public function deleteSite(int $siteId): void
    {
        $site = OrganizationSite::findOrFail($siteId);
        ActivityLogger::log('organization_site.deleted', $site, ['organization_account_id' => $site->organization_account_id]);
        $site->delete();
        $this->resetSiteForm();
        session()->flash('success', 'Site supprimé.');
    }

    public function attachUser(): void
    {
        if (! $this->selectedAccountId || ! $this->user_to_attach) {
            return;
        }

        $this->validate([
            'user_contact_role' => ['nullable', 'string', 'max:100'],
            'user_site_scope_mode' => ['required_without:user_site_scope', Rule::in(['all', 'selected', 'all_sites', 'selected_sites'])],
            'user_site_scope' => ['required_without:user_site_scope_mode', Rule::in(['all', 'selected', 'all_sites', 'selected_sites'])],
            'user_allowed_site_ids' => ['array'],
            'user_allowed_site_ids.*' => ['integer', Rule::exists('organization_sites', 'id')->where(fn ($query) => $query->where('organization_account_id', $this->selectedAccountId))],
            'user_site_ids' => ['array'],
            'user_site_ids.*' => ['integer', Rule::exists('organization_sites', 'id')->where(fn ($query) => $query->where('organization_account_id', $this->selectedAccountId))],
        ]);

        $rawModes = [$this->user_site_scope, $this->user_site_scope_mode];
        $rawMode = collect($rawModes)->first(fn ($value) => in_array($value, ['selected', 'selected_sites'], true));
        if ($rawMode === null) {
            $rawMode = collect($rawModes)->first(fn ($value) => filled($value));
        }
        if ($rawMode === null || $rawMode === '') {
            $rawMode = 'all';
        }

        $scopeMode = match ($rawMode) {
            'selected', 'selected_sites' => 'selected',
            default => 'all',
        };

        $allowedSiteIds = $scopeMode === 'selected'
            ? collect(array_merge($this->user_allowed_site_ids ?: [], $this->user_site_ids ?: []))->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()
            : [];

        $user = User::findOrFail((int) $this->user_to_attach);
        $user->organization_account_id = $this->selectedAccountId;
        if ($this->user_role_mode === 'entreprise') {
            $user->role = User::ROLE_ENTREPRISE;
        }

        $metadata = (array) ($user->metadata ?? []);
        $metadata['entreprise_contact_role'] = $this->user_contact_role ?: null;
        $metadata['entreprise_site_scope'] = ['mode' => $scopeMode, 'allowed_site_ids' => $allowedSiteIds];
        $metadata['entreprise_context'] = ['contact_role' => $this->user_contact_role ?: null, 'site_scope' => $scopeMode, 'allowed_site_ids' => $allowedSiteIds];
        $user->metadata = $metadata;
        $user->save();

        ActivityLogger::log('organization_account.user_attached', $user, [
            'organization_account_id' => $this->selectedAccountId,
            'role' => $user->role,
            'site_scope_mode' => $scopeMode,
            'allowed_site_ids' => data_get($metadata, 'entreprise_site_scope.allowed_site_ids', []),
        ]);

        $this->user_to_attach = '';
        $this->user_contact_role = '';
        $this->user_site_scope_mode = 'all';
        $this->user_site_scope = 'all';
        $this->user_allowed_site_ids = [];
        $this->user_site_ids = [];
        session()->flash('success', 'Utilisateur rattaché au compte entreprise.');
    }

    public function detachUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $accountId = $user->organization_account_id;
        $user->organization_account_id = null;
        if ($user->role === User::ROLE_ENTREPRISE) {
            $user->role = 'client';
        }
        $user->save();

        ActivityLogger::log('organization_account.user_detached', $user, ['organization_account_id' => $accountId]);
        session()->flash('success', 'Utilisateur détaché du compte entreprise.');
    }

    protected function resolvePostalCodeReference(?string $code): ?PostalCode
    {
        if (! filled($code)) {
            return null;
        }

        return PostalCode::query()->where('code', trim($code))->where('is_active', true)->first();
    }

    protected function resolveZoneIdFromPostalCode(?PostalCode $postalCode): ?int
    {
        if (! $postalCode) {
            return null;
        }

        return $postalCode->serviceZones()->whereIn('status', ['active', 'paused'])->orderByDesc('service_zone_postal_code.is_primary')->orderBy('priority')->value('service_zones.id');
    }

    protected function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'compte-entreprise';
        $slug = $base;
        $i = 1;

        while (OrganizationAccount::query()->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
