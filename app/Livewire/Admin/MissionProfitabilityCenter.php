<?php

namespace App\Livewire\Admin;

use App\Models\Mission;
use App\Services\Finance\MissionProfitabilityService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class MissionProfitabilityCenter extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    protected $paginationTheme = 'tailwind';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render(MissionProfitabilityService $profitability): View
    {
        $missions = Mission::query()
            ->with(['rendezVous.client', 'rendezVous.serviceCatalog', 'leadEmployee'])
            ->when($this->status, fn ($query) => $query->where('status', $this->status))
            ->when($this->dateFrom, fn ($query) => $query->whereDate('planned_start_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($query) => $query->whereDate('planned_start_at', '<=', $this->dateTo))
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('leadEmployee', fn ($employeeQuery) => $employeeQuery->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('rendezVous.client', fn ($clientQuery) => $clientQuery->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('rendezVous', fn ($rdvQuery) => $rdvQuery->where('booking_reference', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('planned_start_at')
            ->paginate(10);

        $rows = $missions->getCollection()
            ->map(function (Mission $mission) use ($profitability) {
                return [
                    'mission' => $mission,
                    'profitability' => $profitability->calculate($mission),
                ];
            });

        $missions->setCollection($rows);

        return view('livewire.admin.mission-profitability-center', [
            'missions' => $missions,
        ]);
    }
}