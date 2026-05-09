<?php

namespace App\Livewire\Provider;

use App\Models\ProviderPayout;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Phase 13 — Page web "Mes versements" pour le prestataire.
 *
 * Route : /provider/payouts
 *
 * Affiche :
 *   - Solde du mois (paid + pending)
 *   - Filtres status / dates
 *   - Tableau paginé des payouts
 */
class ProviderPayoutsPage extends Component
{
    use WithPagination;

    public ?string $status = null;
    public ?string $fromDate = null;
    public ?string $toDate = null;

    protected $queryString = [
        'status'   => ['except' => null],
        'fromDate' => ['except' => null],
        'toDate'   => ['except' => null],
    ];

    public function updatingStatus(): void   { $this->resetPage(); }
    public function updatingFromDate(): void { $this->resetPage(); }
    public function updatingToDate(): void   { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->status = null;
        $this->fromDate = null;
        $this->toDate = null;
        $this->resetPage();
    }

    public function getPayoutsProperty()
    {
        $userId = Auth::id();

        return ProviderPayout::query()
            ->forProvider($userId)
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->fromDate, fn ($q) => $q->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('created_at', '<=', $this->toDate))
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    public function getSummaryProperty(): array
    {
        $userId = Auth::id();
        $base = ProviderPayout::query()->forProvider($userId);

        $thisMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd   = now()->subMonth()->endOfMonth();

        return [
            'this_month_paid'    => (float) (clone $base)->paid()->where('created_at', '>=', $thisMonthStart)->sum('amount'),
            'this_month_pending' => (float) (clone $base)->pending()->where('created_at', '>=', $thisMonthStart)->sum('amount'),
            'last_month_paid'    => (float) (clone $base)->paid()->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->sum('amount'),
            'all_time_paid'      => (float) (clone $base)->paid()->sum('amount'),
            'all_time_pending'   => (float) (clone $base)->pending()->sum('amount'),
        ];
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.provider.provider-payouts-page', [
            'payouts' => $this->payouts,
            'summary' => $this->summary,
        ]);
    }
}
