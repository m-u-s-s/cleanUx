<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class HistoriqueClient extends Component
{
    use WithPagination;

    public string $search = '';
    public string $tri = 'desc';
    public string $feedbackStatus = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'tri' => ['except' => 'desc'],
        'feedbackStatus' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTri(): void
    {
        $this->resetPage();
    }

    public function updatingFeedbackStatus(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = Booking::with([
            'employe',
            'feedback',
            'serviceCatalog',
            'serviceZone',
            'organizationSite',
            'postalCode',
            'mission',
            'mission.report',
            'mission.checklists',
            'mission.media',
            'mission.incidents',
        ])
            ->where('client_id', Auth::id())
            ->where('status', 'termine')
            ->when($this->search, fn($q) => $q->searchStructured($this->search))
            ->when($this->feedbackStatus === 'with_feedback', fn($q) => $q->whereHas('feedback'))
            ->when($this->feedbackStatus === 'without_feedback', fn($q) => $q->whereDoesntHave('feedback'))
            ->when($this->dateFrom, fn($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('date', '<=', $this->dateTo));

        return view('livewire.client.historique-client', [
            'historique' => $query
                ->orderBy('date', $this->tri)
                ->orderBy('heure', $this->tri)
                ->paginate(8),
        ]);
    }
}
