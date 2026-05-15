<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
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
            'siteId',
            'site_name',
            'site_code',
            'site_contact_name',
            'site_email',
            'site_phone',
            'site_address_line_1',
            'site_address_line_2',
            'site_city',
            'site_postal_code',
            'site_access_instructions',
            'site_zone_id',
            'site_approval_mode',
            'site_purchase_order_required',
            'site_default_cost_center',
            'site_priority_level',
            'site_requires_manual_validation',
            'site_tags',
            'site_is_primary',
            'site_is_active',
        ]);

        $this->site_approval_mode = 'inherit';
        $this->site_is_active = true;
        $this->site_is_primary = false;
    }

    public function saveSite(): void
    {
        $accountId = $this->currentSelectedAccountId();

        if (! $accountId) {
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

        $zoneId = filled($validated['site_zone_id'] ?? null)
            ? (int) $validated['site_zone_id']
            : $this->resolveZoneIdFromPostalCode($postalCode);

        if ((bool) $this->site_is_primary) {
            OrganizationSite::query()
                ->where('organization_account_id', $accountId)
                ->when($this->siteId, fn($query) => $query->where('id', '!=', $this->siteId))
                ->update(['is_primary' => false]);
        }

        $site = $this->siteId
            ? OrganizationSite::query()
            ->where('organization_account_id', $accountId)
            ->findOrFail((int) $this->siteId)
            : new OrganizationSite();

        $existingMetadata = $this->normalizeMetadata($site->metadata ?? []);

        $site->forceFill([
            'organization_account_id' => $accountId,
            'service_zone_id' => $zoneId,
            'postal_code_id' => $postalCode?->id,
            'name' => $validated['site_name'],
            'site_code' => filled($validated['site_code'] ?? null) ? $validated['site_code'] : null,
            'contact_name' => filled($validated['site_contact_name'] ?? null) ? $validated['site_contact_name'] : null,
            'email' => filled($validated['site_email'] ?? null) ? $validated['site_email'] : null,
            'phone' => filled($validated['site_phone'] ?? null) ? $validated['site_phone'] : null,
            'address_line_1' => filled($validated['site_address_line_1'] ?? null) ? $validated['site_address_line_1'] : null,
            'address_line_2' => filled($validated['site_address_line_2'] ?? null) ? $validated['site_address_line_2'] : null,
            'city' => filled($validated['site_city'] ?? null) ? $validated['site_city'] : ($postalCode?->city_name ?: null),
            'postal_code' => filled($validated['site_postal_code'] ?? null) ? trim($validated['site_postal_code']) : null,
            'access_instructions' => filled($validated['site_access_instructions'] ?? null) ? $validated['site_access_instructions'] : null,
            'is_primary' => (bool) $this->site_is_primary,
            'is_active' => (bool) $this->site_is_active,
            'metadata' => array_replace_recursive($existingMetadata, [
                'approval_mode' => $validated['site_approval_mode'],
                'purchase_order_required' => (bool) ($validated['site_purchase_order_required'] ?? false),
                'default_cost_center' => filled($validated['site_default_cost_center'] ?? null) ? $validated['site_default_cost_center'] : null,
                'priority_level' => filled($validated['site_priority_level'] ?? null) ? $validated['site_priority_level'] : null,
                'requires_manual_validation' => (bool) ($validated['site_requires_manual_validation'] ?? false),
                'site_tags' => collect(explode(',', (string) ($validated['site_tags'] ?? '')))
                    ->map(fn($tag) => trim($tag))
                    ->filter()
                    ->values()
                    ->all(),
                'auto_zone_resolution' => [
                    'postal_code_id' => $postalCode?->id,
                    'postal_code' => $postalCode?->code ?: ($validated['site_postal_code'] ?? null),
                    'service_zone_id' => $zoneId,
                ],
            ]),
        ])->save();

        ActivityLogger::log($this->siteId ? 'organization_site.updated' : 'organization_site.created', $site, [
            'organization_account_id' => $accountId,
            'service_zone_id' => $zoneId,
        ]);

        $this->resetSiteForm();

        session()->flash('success', 'Site entreprise enregistré.');
    }

    public function deleteSite(int $siteId): void
    {
        $site = OrganizationSite::findOrFail($siteId);

        ActivityLogger::log('organization_site.deleted', $site, [
            'organization_account_id' => $site->organization_account_id,
        ]);

        $site->delete();

        $this->resetSiteForm();

        session()->flash('success', 'Site supprimé.');
    }

    public function attachUser(): void
    {
        $accountId = $this->currentSelectedAccountId();

        if (! $accountId) {
            return;
        }

        $this->validate([
            'user_to_attach' => ['required', 'integer', 'exists:users,id'],
            'user_contact_role' => ['nullable', 'string', 'max:80'],
            'user_site_scope' => ['required', 'string', 'in:all,selected,selected_sites'],

            // Compatibilité ancienne/nouvelle propriété Livewire
            'user_allowed_site_ids' => ['nullable', 'array'],
            'user_allowed_site_ids.*' => [
                'integer',
                Rule::exists('organization_sites', 'id')
                    ->where(fn($query) => $query->where('organization_account_id', $accountId)),
            ],
            'user_site_ids' => ['nullable', 'array'],
            'user_site_ids.*' => [
                'integer',
                Rule::exists('organization_sites', 'id')
                    ->where(fn($query) => $query->where('organization_account_id', $accountId)),
            ],
        ]);

        $user = User::query()->findOrFail((int) $this->user_to_attach);

        $siteScope = in_array($this->user_site_scope, ['selected', 'selected_sites'], true)
            ? 'selected'
            : 'all';

        $rawSiteIds = collect($this->user_allowed_site_ids ?? [])
            ->merge($this->user_site_ids ?? [])
            ->filter(fn($id) => filled($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $allowedSiteIds = $siteScope === 'selected'
            ? OrganizationSite::query()
            ->where('organization_account_id', $accountId)
            ->whereIn('id', $rawSiteIds)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all()
            : [];

        $metadata = $this->normalizeMetadata($user->metadata ?? []);

        Arr::set($metadata, 'entreprise_context.organization_account_id', (int) $accountId);
        Arr::set($metadata, 'entreprise_context.contact_role', $this->user_contact_role ?: null);
        Arr::set($metadata, 'entreprise_context.site_scope', $siteScope);
        Arr::set($metadata, 'entreprise_context.allowed_site_ids', $allowedSiteIds);

        Arr::set($metadata, 'entreprise_site_scope.mode', $siteScope);
        Arr::set($metadata, 'entreprise_site_scope.allowed_site_ids', $allowedSiteIds);

        $metadata['entreprise_contact_role'] = $this->user_contact_role ?: null;

        $user->forceFill([
            'role' => User::ROLE_ENTREPRISE,
            'organization_account_id' => (int) $accountId,
            'metadata' => $metadata,
        ])->save();

        $this->reset([
            'user_to_attach',
            'user_contact_role',
            'user_allowed_site_ids',
        ]);

        if (property_exists($this, 'user_site_ids')) {
            $this->reset('user_site_ids');
        }

        $this->user_site_scope = 'all';

        session()->flash('success', 'Utilisateur rattaché au compte entreprise.');
    }

    public function detachUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $accountId = $user->organization_account_id;

        $metadata = $this->normalizeMetadata($user->metadata ?? []);

        unset(
            $metadata['entreprise_contact_role'],
            $metadata['entreprise_site_scope'],
            $metadata['entreprise_context']
        );

        $user->forceFill([
            'organization_account_id' => null,
            'role' => $user->role === User::ROLE_ENTREPRISE ? 'client' : $user->role,
            'metadata' => $metadata,
        ])->save();

        ActivityLogger::log('organization_account.user_detached', $user, [
            'organization_account_id' => $accountId,
        ]);

        session()->flash('success', 'Utilisateur détaché du compte entreprise.');
    }

    protected function resolvePostalCodeReference(?string $code): ?PostalCode
    {
        if (! filled($code)) {
            return null;
        }

        return PostalCode::query()
            ->where('code', trim($code))
            ->where('is_active', true)
            ->first();
    }

    protected function resolveZoneIdFromPostalCode(?PostalCode $postalCode): ?int
    {
        if (! $postalCode) {
            return null;
        }

        return $postalCode->serviceZones()
            ->whereIn('service_zones.status', ['active', 'paused'])
            ->orderByDesc('service_zone_postal_code.is_primary')
            ->orderBy('service_zones.priority')
            ->value('service_zones.id');
    }

    protected function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'compte-entreprise';
        $slug = $base;
        $i = 1;

        while (
            OrganizationAccount::query()
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function currentSelectedAccountId(): ?int
    {
        $accountId = $this->selectedAccountId ?? null;

        if (! $accountId && isset($this->accountId)) {
            $accountId = $this->accountId;
        }

        return $accountId ? (int) $accountId : null;
    }

    private function normalizeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
