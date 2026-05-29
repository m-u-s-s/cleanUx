<?php

namespace App\Support\Livewire\Concerns\Booking;

use App\Models\OrganizationSite;
use Illuminate\Support\Facades\Auth;

trait InteractsWithBookingOrganization
{
    public function isPremiumClient(): bool
    {
        return Auth::check()
            && ! Auth::user()->isEntreprise()
            && Auth::user()->canChooseEmployee();
    }

    public function isEntrepriseCustomer(): bool
    {
        return Auth::check()
            && Auth::user()->isEntreprise()
            && filled(Auth::user()->organization_account_id);
    }

    public function getOrganizationSitesProperty()
    {
        $organizationAccountId = $this->bookingOrganizationAccountId();

        if (! $organizationAccountId) {
            return collect();
        }

        return OrganizationSite::query()
            ->where('organization_account_id', $organizationAccountId)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    protected function bookingOrganizationAccountId($user = null): ?int
    {
        $user ??= Auth::user();

        $id = data_get($user, 'organization_account_id')
            ?: data_get($user, 'current_organization_id');

        if (! $id && property_exists($this, 'organization_account_id')) {
            $id = $this->organization_account_id;
        }

        return filled($id) ? (int) $id : null;
    }

    protected function selectedOrganizationSite(): ?OrganizationSite
    {
        if (! $this->organization_site_id) {
            return null;
        }

        $organizationAccountId = $this->bookingOrganizationAccountId();

        if (! $organizationAccountId) {
            return null;
        }

        return OrganizationSite::query()
            ->where('organization_account_id', $organizationAccountId)
            ->where('id', (int) $this->organization_site_id)
            ->first();
    }

    public function updatedOrganizationSiteId(): void
    {
        $this->resetErrorBag(['organization_site_id', 'postal_code_input']);

        $site = $this->selectedOrganizationSite();

        if ($site) {
            $this->applyOrganizationSite($site);

            $policy = $this->currentEntreprisePolicy($site);

            if (! filled($this->cost_center) && filled($policy['default_cost_center'] ?? null)) {
                $this->cost_center = (string) $policy['default_cost_center'];
            }
        }

        $this->resolveCoverageContext();
        $this->chargerEmployesDisponibles();
        $this->chargerCreneauxDisponibles();
        $this->refreshEstimations();
    }

    protected function applyOrganizationSite(OrganizationSite $site, bool $overwriteAddress = true): void
    {
        if ($overwriteAddress) {
            $this->adresse = $site->address_line_1 ?: $this->adresse;
            $this->ville = $site->city ?: $this->ville;
            $this->postal_code_input = $site->postal_code ?: $this->postal_code_input;
        }

        $this->organization_site_id = $site->id;
        $this->site_contact_name = $this->site_contact_name ?: $site->contact_name;
        $this->site_contact_phone = $this->site_contact_phone ?: $site->phone;
        $this->site_instructions = $this->site_instructions ?: $site->access_instructions;
    }

    protected function currentEntreprisePolicy(?OrganizationSite $site = null): array
    {
        if (! $this->isEntrepriseCustomer()) {
            return [
                'approval_mode' => 'auto',
                'approval_required' => false,
                'purchase_order_required' => false,
                'default_cost_center' => null,
            ];
        }

        $site = $site ?? $this->selectedOrganizationSite();
        $account = Auth::user()?->organizationAccount;

        $accountMeta = (array) ($account?->metadata ?? []);
        $siteMeta = (array) ($site?->metadata ?? []);

        $approvalMode = (string) ($siteMeta['approval_mode'] ?? 'inherit');
        if ($approvalMode === 'inherit' || $approvalMode === '') {
            $approvalMode = (string) ($accountMeta['approval_mode'] ?? 'auto');
        }

        $purchaseOrderRequired = array_key_exists('purchase_order_required', $siteMeta)
            ? (bool) $siteMeta['purchase_order_required']
            : (bool) ($accountMeta['purchase_order_required'] ?? false);

        $defaultCostCenter = $siteMeta['default_cost_center'] ?? ($accountMeta['default_cost_center'] ?? null);

        return [
            'approval_mode' => $approvalMode ?: 'auto',
            'approval_required' => $approvalMode === 'manual',
            'purchase_order_required' => $purchaseOrderRequired,
            'default_cost_center' => $defaultCostCenter,
        ];
    }
}
