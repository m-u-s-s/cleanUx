<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\ActivityLog;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ManagesEntrepriseAccounts
{
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingType(): void { $this->resetPage(); }
    public function updatingZoneFilter(): void { $this->resetPage(); }

    public function updatedName(string $value): void
    {
        if (! $this->accountId) {
            $this->slug = Str::slug($value);
        }
    }

    public function getZonesProperty()
    {
        return ServiceZone::query()->where('status', 'active')->orderBy('name')->get();
    }

    public function getSelectedAccountProperty(): ?OrganizationAccount
    {
        if (! $this->selectedAccountId) {
            return null;
        }

        return OrganizationAccount::with(['sites.serviceZone', 'users'])->find($this->selectedAccountId);
    }

    public function getAvailableUsersProperty()
    {
        return User::query()
            ->whereIn('role', [User::ROLE_CLIENT, User::ROLE_ENTREPRISE])
            ->where(function ($query) {
                $query->whereNull('organization_account_id');
                if ($this->selectedAccountId) {
                    $query->orWhere('organization_account_id', $this->selectedAccountId);
                }
            })
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    public function selectAccount(int $accountId): void
    {
        $account = OrganizationAccount::with('sites')->findOrFail($accountId);

        $this->selectedAccountId = $account->id;
        $this->accountId = $account->id;
        $this->name = (string) $account->name;
        $this->legal_name = (string) ($account->legal_name ?? '');
        $this->slug = (string) ($account->slug ?? '');
        $this->account_type = (string) ($account->type ?: 'entreprise');
        $this->tva_number = (string) ($account->tva_number ?? '');
        $this->email = (string) ($account->email ?? '');
        $this->phone = (string) ($account->phone ?? '');
        $this->billing_email = (string) ($account->billing_email ?? '');
        $this->account_status = (string) ($account->status ?: 'active');
        $this->address_line_1 = (string) ($account->address_line_1 ?? '');
        $this->address_line_2 = (string) ($account->address_line_2 ?? '');
        $this->city = (string) ($account->city ?? '');
        $this->postal_code = (string) ($account->postal_code ?? '');
        $this->is_multisite = (bool) $account->is_multisite;
        $this->is_key_account = (bool) $account->is_key_account;
        $this->notes = (string) ($account->notes ?? '');
        $this->contract_reference = (string) data_get($account->metadata, 'contract_reference', '');
        $this->pricing_profile = (string) data_get($account->metadata, 'pricing_profile', '');
        $this->sla_hours = (string) data_get($account->metadata, 'sla_hours', '');
        $priorityIds = (array) data_get($account->metadata, 'priority_zone_ids', []);
        if ($priorityIds === [] && filled(data_get($account->metadata, 'priority_zone_id'))) {
            $priorityIds = [(int) data_get($account->metadata, 'priority_zone_id')];
        }
        $this->priority_zone_id = (string) data_get($account->metadata, 'priority_zone_id', '');
        $this->zone_priority_ids = collect($priorityIds)->map(fn ($id) => (string) $id)->values()->all();
        $this->approval_mode = (string) data_get($account->metadata, 'approval_mode', 'auto');
        $this->purchase_order_required = (bool) data_get($account->metadata, 'purchase_order_required', data_get($account->metadata, 'require_po', false));
        $this->require_po = $this->purchase_order_required;
        $this->contract_status_value = (string) data_get($account->metadata, 'contract_status', 'draft');
        $this->default_cost_center = (string) data_get($account->metadata, 'default_cost_center', '');
        $this->negotiated_discount_percent = (string) data_get($account->metadata, 'negotiated_discount_percent', '');
        $this->payment_terms_days = (string) data_get($account->metadata, 'payment_terms_days', '');
        $this->resetSiteForm();
    }

    public function resetAccountForm(): void
    {
        $this->reset([
            'selectedAccountId', 'accountId', 'name', 'legal_name', 'slug', 'tva_number', 'email', 'phone', 'billing_email',
            'address_line_1', 'address_line_2', 'city', 'postal_code', 'notes', 'contract_reference', 'pricing_profile',
            'sla_hours', 'priority_zone_id', 'approval_mode', 'purchase_order_required', 'default_cost_center',
            'negotiated_discount_percent', 'payment_terms_days', 'contract_status_value', 'zone_priority_ids', 'require_po', 'user_to_attach',
        ]);

        $this->account_type = 'entreprise';
        $this->account_status = 'active';
        $this->is_multisite = true;
        $this->is_key_account = false;
        $this->contract_status_value = 'draft';
        $this->user_role_mode = 'keep';
        $this->user_site_scope_mode = 'all';
        $this->user_site_scope = 'all';
        $this->user_allowed_site_ids = [];
        $this->user_site_ids = [];
        $this->require_po = false;
        $this->resetSiteForm();
        $this->resetValidation();
    }

    public function saveAccount(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('organization_accounts', 'slug')->ignore($this->accountId)],
            'account_type' => ['required', Rule::in(['individual', 'business', 'entreprise', 'partner'])],
            'tva_number' => ['nullable', 'string', 'max:50', Rule::unique('organization_accounts', 'tva_number')->ignore($this->accountId)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'account_status' => ['required', Rule::in(['active', 'inactive', 'prospect', 'suspended'])],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'contract_reference' => ['nullable', 'string', 'max:255'],
            'pricing_profile' => ['nullable', 'string', 'max:100'],
            'sla_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'priority_zone_id' => ['nullable', 'integer', Rule::exists('service_zones', 'id')],
            'zone_priority_ids' => ['array'],
            'zone_priority_ids.*' => ['integer', Rule::exists('service_zones', 'id')],
            'approval_mode' => ['nullable', 'string', 'max:100'],
            'purchase_order_required' => ['boolean'],
            'require_po' => ['boolean'],
            'default_cost_center' => ['nullable', 'string', 'max:100'],
            'negotiated_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'contract_status_value' => ['required', Rule::in(['draft', 'active', 'paused', 'terminated'])],
        ]);

        $postalCode = $this->resolvePostalCodeReference($validated['postal_code'] ?? null);
        $defaultPriorityZoneId = $validated['priority_zone_id'] !== '' ? (int) $validated['priority_zone_id'] : null;
        $priorityZoneIds = collect($validated['zone_priority_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if ($defaultPriorityZoneId && ! in_array($defaultPriorityZoneId, $priorityZoneIds, true)) {
            array_unshift($priorityZoneIds, $defaultPriorityZoneId);
        }
        if ($defaultPriorityZoneId === null && ! empty($priorityZoneIds)) {
            $defaultPriorityZoneId = $priorityZoneIds[0];
        }

        $resolvedApprovalMode = isset($validated['approval_mode']) ? trim((string) $validated['approval_mode']) : 'auto';
        if ($resolvedApprovalMode === '') {
            $resolvedApprovalMode = 'auto';
        }

        $purchaseOrderRequired = (bool) (($validated['purchase_order_required'] ?? false) || ($validated['require_po'] ?? false));

        $account = OrganizationAccount::updateOrCreate(
            ['id' => $this->accountId],
            [
                'name' => $validated['name'],
                'legal_name' => $validated['legal_name'] ?: null,
                'slug' => $validated['slug'] ?: $this->makeUniqueSlug($validated['name'], $this->accountId),
                'type' => $validated['account_type'],
                'tva_number' => $validated['tva_number'] ?: null,
                'email' => $validated['email'] ?: null,
                'phone' => $validated['phone'] ?: null,
                'billing_email' => $validated['billing_email'] ?: null,
                'status' => $validated['account_status'],
                'address_line_1' => $validated['address_line_1'] ?: null,
                'address_line_2' => $validated['address_line_2'] ?: null,
                'city' => $validated['city'] ?: ($postalCode?->city_name ?: null),
                'postal_code' => $validated['postal_code'] ?: null,
                'postal_code_id' => $postalCode?->id,
                'is_multisite' => $this->is_multisite,
                'is_key_account' => $this->is_key_account,
                'notes' => $validated['notes'] ?: null,
                'metadata' => [
                    'contract_reference' => $validated['contract_reference'] ?: null,
                    'pricing_profile' => $validated['pricing_profile'] ?: null,
                    'sla_hours' => $validated['sla_hours'] !== '' ? (int) $validated['sla_hours'] : null,
                    'priority_zone_id' => $defaultPriorityZoneId,
                    'priority_zone_ids' => $priorityZoneIds,
                    'approval_mode' => $resolvedApprovalMode,
                    'purchase_order_required' => $purchaseOrderRequired,
                    'require_po' => $purchaseOrderRequired,
                    'default_cost_center' => $validated['default_cost_center'] ?: null,
                    'negotiated_discount_percent' => $validated['negotiated_discount_percent'] !== '' ? (float) $validated['negotiated_discount_percent'] : null,
                    'payment_terms_days' => $validated['payment_terms_days'] !== '' ? (int) $validated['payment_terms_days'] : null,
                    'contract_status' => $validated['contract_status_value'],
                ],
            ]
        );

        ActivityLogger::log($this->accountId ? 'organization_account.updated' : 'organization_account.created', $account, [
            'type' => $account->type,
            'status' => $account->status,
            'priority_zone_id' => data_get($account->metadata, 'priority_zone_id'),
            'priority_zone_ids' => data_get($account->metadata, 'priority_zone_ids', []),
        ]);

        $this->selectAccount($account->id);
        session()->flash('success', 'Compte entreprise enregistré.');
    }

    protected function entrepriseActivityLogs()
    {
        if (! $this->selectedAccountId) {
            return collect();
        }

        return ActivityLog::query()
            ->with('user')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('target_type', OrganizationAccount::class)->where('target_id', $this->selectedAccountId);
                })->orWhere(function ($q) {
                    $q->where('target_type', OrganizationSite::class)
                        ->whereIn('target_id', OrganizationSite::where('organization_account_id', $this->selectedAccountId)->pluck('id'));
                });
            })
            ->latest()
            ->limit(12)
            ->get();
    }
}
