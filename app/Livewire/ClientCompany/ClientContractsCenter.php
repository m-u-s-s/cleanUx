<?php

namespace App\Livewire\ClientCompany;

use App\Models\ContractSlaEvent;
use App\Models\OrganizationContract;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Portail client société — lecture seule des contrats-cadres B2B où
 * l'organisation courante est la partie cliente. Isolation inter-org
 * STRICTE : la requête filtre sur `organization_account_id`.
 *
 * @property-read Collection<int, OrganizationContract> $contracts
 */
class ClientContractsCenter extends Component
{
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
        ])->layout('layouts.client-company');
    }
}
