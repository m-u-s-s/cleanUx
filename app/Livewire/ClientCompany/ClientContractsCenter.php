<?php

namespace App\Livewire\ClientCompany;

use App\Models\ContractSlaEvent;
use App\Models\OrganizationContract;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Portail client société — lecture seule des contrats-cadres B2B où l'organisation courante est la partie cliente.
 *
 * @property-read Collection<int, OrganizationContract> $contracts
 * @property-read ?OrganizationContract $selectedContract
 */
class ClientContractsCenter extends Component
{
    use EnforcesActiveOrgMembership;

    public ?int $selectedContractId = null;

    /** Ouvre le détail d'un contrat — org-safe : ne sélectionne QUE si le contrat appartient à l'org du membre courant. */
    public function viewContract(int $contractId): void
    {
        $orgId = Auth::user()?->organizationContextId();

        $exists = OrganizationContract::query()
            ->where('id', $contractId)
            ->where('organization_account_id', $orgId)
            ->exists();

        $this->selectedContractId = $exists ? $contractId : null;
    }

    public function closeContract(): void
    {
        $this->selectedContractId = null;
    }

    /** Détail du contrat sélectionné — re-filtré sur l'org du membre. */
    public function getSelectedContractProperty(): ?OrganizationContract
    {
        if (! $this->selectedContractId) {
            return null;
        }

        $orgId = Auth::user()?->organizationContextId();

        return OrganizationContract::query()
            ->where('id', $this->selectedContractId)
            ->where('organization_account_id', $orgId)
            ->with([
                'providerOrganization:id,name',
                'rateCards.serviceCatalog:id,name',
                'workOrders',
            ])
            ->withCount([
                'slaEvents as sla_breached_count' => fn ($q) => $q->where('status', ContractSlaEvent::STATUS_BREACHED),
            ])
            ->first();
    }

    /** @return Collection<int, OrganizationContract> */
    public function getContractsProperty(): Collection
    {
        $orgId = Auth::user()?->organizationContextId();

        if (! $orgId) {
            return collect();
        }

        return OrganizationContract::query()
            ->where('organization_account_id', $orgId)
            ->with(['providerOrganization:id,name', 'rateCards', 'workOrders'])
            ->withCount([
                'slaEvents as sla_breached_count' => fn ($q) => $q->where('status', ContractSlaEvent::STATUS_BREACHED),
            ])
            ->orderByDesc('effective_from')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.client-company.client-contracts-center', [
            'contracts' => $this->contracts,
            'selectedContract' => $this->selectedContract,
        ])->layout('layouts.client-company');
    }
}
