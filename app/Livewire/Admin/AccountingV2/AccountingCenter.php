<?php

namespace App\Livewire\Admin\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\AccountingExport;
use App\Models\AccountingPeriod;
use App\Services\AccountingV2\ExportManager;
use App\Services\AccountingV2\PeriodCloser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class AccountingCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'ledger';   // ledger | periods | exports
    public string $filterJournal = '';
    public string $filterAccount = '';
    public int $filterYear = 0;
    public int $filterMonth = 0;

    public int $exportYear;
    public int $exportMonth;
    public string $exportFormat = 'csv';

    public function mount(): void
    {
        $this->filterYear = (int) now()->year;
        $this->filterMonth = (int) now()->month;
        $this->exportYear = $this->filterYear;
        $this->exportMonth = $this->filterMonth;
    }

    public function closePeriod(int $year, int $month): void
    {
        try {
            app(PeriodCloser::class)->close($year, $month, Auth::user());
            $this->dispatch('toast', "Période {$year}-{$month} clôturée.", 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function generateExport(): void
    {
        try {
            app(ExportManager::class)->generate(
                $this->exportFormat,
                $this->exportYear,
                $this->exportMonth ?: null,
                Auth::id(),
            );
            $this->dispatch('toast', 'Export généré.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'entries_total' => AccountingEntry::query()->count(),
            'periods_closed' => AccountingPeriod::query()->where('is_closed', true)->count(),
            'exports_ready' => AccountingExport::query()->where('status', AccountingExport::STATUS_READY)->count(),
            'period_label' => sprintf('%04d-%02d', $this->filterYear, $this->filterMonth),
            'period_debit' => (int) AccountingEntry::query()
                ->forPeriod($this->filterYear, $this->filterMonth)
                ->sum('debit_cents'),
            'period_credit' => (int) AccountingEntry::query()
                ->forPeriod($this->filterYear, $this->filterMonth)
                ->sum('credit_cents'),
        ];

        if ($this->tab === 'ledger') {
            $items = AccountingEntry::query()
                ->forPeriod($this->filterYear, $this->filterMonth)
                ->when($this->filterJournal, fn ($q) => $q->where('journal_code', $this->filterJournal))
                ->when($this->filterAccount, fn ($q) => $q->where('account_code', $this->filterAccount))
                ->orderByDesc('posting_date')
                ->orderByDesc('id')
                ->paginate(25);
        } elseif ($this->tab === 'periods') {
            $items = AccountingPeriod::query()
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->paginate(20);
        } else {
            $items = AccountingExport::query()
                ->orderByDesc('created_at')
                ->paginate(20);
        }

        return view('livewire.admin.accounting-v2.accounting-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
