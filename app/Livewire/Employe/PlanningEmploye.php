<?php

namespace App\Livewire\Employe;

use App\Models\RendezVous;
use App\Models\ServiceZone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PlanningEmploye extends Component
{
    public string $viewMode = 'week';
    public string $anchorDate = '';
    public string $status = '';
    public string $zoneId = '';
    public string $service = '';

    public function mount(): void
    {
        $this->anchorDate = now()->toDateString();
    }

    public function setViewMode(string $mode): void
    {
        if (!in_array($mode, ['day', 'week', 'month'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    public function previousPeriod(): void
    {
        $anchor = Carbon::parse($this->anchorDate);

        $this->anchorDate = match ($this->viewMode) {
            'day' => $anchor->subDay()->toDateString(),
            'month' => $anchor->subMonth()->toDateString(),
            default => $anchor->subWeek()->toDateString(),
        };
    }

    public function nextPeriod(): void
    {
        $anchor = Carbon::parse($this->anchorDate);

        $this->anchorDate = match ($this->viewMode) {
            'day' => $anchor->addDay()->toDateString(),
            'month' => $anchor->addMonth()->toDateString(),
            default => $anchor->addWeek()->toDateString(),
        };
    }

    public function goToday(): void
    {
        $this->anchorDate = now()->toDateString();
    }

    protected function range(): array
    {
        $anchor = Carbon::parse($this->anchorDate);

        return match ($this->viewMode) {
            'day' => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
        };
    }

    protected function baseQuery()
    {
        [$start, $end] = $this->range();

        return RendezVous::with(['client', 'serviceZone', 'organizationSite', 'serviceCatalog', 'postalCode'])
            ->where('employe_id', Auth::id())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->zoneId !== '', fn ($query) => $query->where('service_zone_id', $this->zoneId))
            ->when($this->service !== '', fn ($query) => $query->whereServiceMatches($this->service));
    }

    public function getPlanningStatsProperty(): array
    {
        $missions = (clone $this->baseQuery())->get();

        return [
            'total' => $missions->count(),
            'en_attente' => $missions->where('status', 'en_attente')->count(),
            'confirmees' => $missions->where('status', 'confirme')->count(),
            'en_cours' => $missions->whereIn('status', ['en_route', 'sur_place'])->count(),
            'terminees' => $missions->where('status', 'termine')->count(),
        ];
    }

    public function getGroupedMissionsProperty()
    {
        return (clone $this->baseQuery())
            ->orderBy('date')
            ->orderBy('heure')
            ->get()
            ->groupBy(fn ($rdv) => optional($rdv->date)->format('Y-m-d'));
    }

    public function getPeriodLabelProperty(): string
    {
        [$start, $end] = $this->range();

        if ($this->viewMode === 'day') {
            return $start->translatedFormat('l d F Y');
        }

        if ($this->viewMode === 'month') {
            return $start->translatedFormat('F Y');
        }

        return $start->translatedFormat('d M').' → '.$end->translatedFormat('d M Y');
    }

    public function render()
    {
        $zones = ServiceZone::query()
            ->whereHas('employeeAssignments', fn ($query) => $query->where('user_id', Auth::id())->where('is_active', true))
            ->orderBy('name')
            ->get();

        return view('livewire.employe.planning-employe', [
            'zones' => $zones,
            'groupedMissions' => $this->groupedMissions,
            'planningStats' => $this->planningStats,
            'periodLabel' => $this->periodLabel,
        ])->layout('layouts.app');
    }
}
