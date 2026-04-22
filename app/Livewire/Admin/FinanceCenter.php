<?php

namespace App\Livewire\Admin;

use App\Support\Livewire\Concerns\Admin\BuildsFinanceCenterQueries;
use App\Support\Livewire\Concerns\Admin\HandlesFinanceDocuments;
use Livewire\Component;
use Livewire\WithPagination;

class FinanceCenter extends Component
{
    use WithPagination;
    use BuildsFinanceCenterQueries;
    use HandlesFinanceDocuments;

    protected string $paginationTheme = 'tailwind';

    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $status = '';
    public string $zoneId = '';
    public string $serviceId = '';
    public string $organizationId = '';
    public string $market = '';
    public string $viewMode = 'all';
    public string $paymentFilter = '';
    public int $selectedRendezVousId = 0;
    public float $taxRate = 21.0;
    public string $manualPaymentAmount = '';
    public string $manualPaymentMethod = 'manual';

    protected $queryString = [
        'search', 'dateFrom', 'dateTo', 'status', 'zoneId', 'serviceId', 'organizationId', 'market', 'viewMode', 'paymentFilter', 'page',
    ];

    public function mount(): void
    {
        if (blank($this->dateFrom)) {
            $this->dateFrom = now()->startOfMonth()->toDateString();
        }

        if (blank($this->dateTo)) {
            $this->dateTo = now()->endOfMonth()->toDateString();
        }
    }

    public function render()
    {
        return view('livewire.admin.finance-center', [
            'rows' => $this->rows,
            'kpis' => $this->kpis,
            'selectedRendezVous' => $this->selectedRendezVous,
        ]);
    }
}
