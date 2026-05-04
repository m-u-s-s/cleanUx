<?php

namespace App\Livewire\ClientCompany;

use App\Models\OrganizationSite;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class BillingCenter extends Component
{
    use WithPagination;

    public string $filterStatus = '';
    public ?int   $filterSiteId = null;
    public string $filterPeriod = 'month';
    public string $searchRef    = '';

    public function mount(): void
    {
        abort_unless(
            app(PermissionService::class)->can(Auth::user(), 'finance.view', Auth::user()->currentOrganization),
            403
        );
    }

    private function periodDates(): array
    {
        return match ($this->filterPeriod) {
            'quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'year'    => [now()->startOfYear(), now()->endOfYear()],
            'all'     => [now()->subYears(10), now()],
            default   => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    public function getSummaryProperty(): array
    {
        $orgId = Auth::user()->current_organization_id;
        [$from, $to] = $this->periodDates();

        // Données simulées — à connecter à Invoice model
        return [
            'total_period'  => 0,
            'total_unpaid'  => 0,
            'count_overdue' => 0,
            'from'          => $from->format('d/m/Y'),
            'to'            => $to->format('d/m/Y'),
        ];
    }

    public function getSitesProperty()
    {
        return OrganizationSite::forOrg(Auth::user()->current_organization_id)
            ->active()->orderBy('name')->get(['id', 'name']);
    }

    public function render()
    {
        return view('livewire.client-company.billing-center', [
            'summary' => $this->summaryProperty,
            'sites'   => $this->sitesProperty,
        ])->layout('layouts.client-company');
    }
}
