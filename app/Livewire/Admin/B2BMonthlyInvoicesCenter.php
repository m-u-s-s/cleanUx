<?php

namespace App\Livewire\Admin;

use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Services\Finance\B2BMonthlyInvoiceService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class B2BMonthlyInvoicesCenter extends Component
{
    use WithPagination;

    public ?int $organization_account_id = null;
    public string $period_start = '';
    public string $period_end = '';

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->period_start = now()->subMonth()->startOfMonth()->toDateString();
        $this->period_end = now()->subMonth()->endOfMonth()->toDateString();
    }

    public function generate(B2BMonthlyInvoiceService $service): void
    {
        $this->validate([
            'organization_account_id' => ['required', 'exists:organization_accounts,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $organization = OrganizationAccount::findOrFail($this->organization_account_id);

        $invoice = $service->generateForOrganization(
            $organization,
            $this->period_start,
            $this->period_end
        );

        if (! $invoice) {
            $this->dispatch('toast', 'Aucun rendez-vous facturable pour cette période.', 'warning');
            return;
        }

        $this->dispatch('toast', 'Facture B2B générée : '.$invoice->invoice_number, 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.b2b-monthly-invoices-center', [
            'organizations' => OrganizationAccount::query()
                ->whereIn('status', ['active', 'pilot', 'signed'])
                ->orderBy('name')
                ->get(),

            'invoices' => FinanceInvoice::query()
                ->with('organizationAccount')
                ->where('invoice_type', 'b2b_monthly')
                ->latest('issued_at')
                ->paginate(10),
        ]);
    }
}