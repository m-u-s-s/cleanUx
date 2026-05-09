<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class CalendrierInterne extends Component
{
    public string $viewMode = 'dayGridMonth';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $status = '';
    public string $zoneId = '';
    public string $serviceId = '';
    public string $employeId = '';
    public string $search = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
    }

    public function resetFilters(): void
    {
        $this->viewMode = 'dayGridMonth';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
        $this->status = '';
        $this->zoneId = '';
        $this->serviceId = '';
        $this->employeId = '';
        $this->search = '';
    }

    public function setPreset(string $preset): void
    {
        match ($preset) {
            'today' => [$this->dateFrom, $this->dateTo] = [today()->toDateString(), today()->toDateString()],
            'week' => [$this->dateFrom, $this->dateTo] = [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [$this->dateFrom, $this->dateTo] = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            default => null,
        };
    }

    public function getZonesProperty()
    {
        return ServiceZone::orderBy('name')->get(['id', 'name']);
    }

    public function getServicesProperty()
    {
        return ServiceCatalog::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function getEmployesProperty()
    {
        return User::where('role', 'employe')->orderBy('name')->get(['id', 'name']);
    }

    protected function baseQuery(): Builder
    {
        return Booking::query()
            ->with(['client:id,name', 'employe:id,name', 'serviceCatalog:id,name', 'serviceZone:id,name'])
            ->when($this->dateFrom, fn (Builder $q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn (Builder $q) => $q->whereDate('date', '<=', $this->dateTo))
            ->when($this->status, fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->zoneId, fn (Builder $q) => $q->where('service_zone_id', $this->zoneId))
            ->when($this->serviceId, fn (Builder $q) => $q->where('service_catalog_id', $this->serviceId))
            ->when($this->employeId, fn (Builder $q) => $q->where('employe_id', $this->employeId))
            ->when($this->search, fn (Builder $q) => $q->searchStructured($this->search));
    }

    public function getStatsProperty(): array
    {
        $query = $this->baseQuery();

        return [
            'total' => (clone $query)->count(),
            'en_attente' => (clone $query)->where('status', 'en_attente')->count(),
            'confirme' => (clone $query)->where('status', 'confirme')->count(),
            'terrain' => (clone $query)->whereIn('status', ['en_route', 'sur_place'])->count(),
            'termine' => (clone $query)->where('status', 'termine')->count(),
        ];
    }

    protected function eventColor(string $status): string
    {
        return match ($status) {
            'confirme' => '#10b981',
            'en_route' => '#3b82f6',
            'sur_place' => '#8b5cf6',
            'termine' => '#0f172a',
            'refuse' => '#ef4444',
            default => '#f59e0b',
        };
    }

    public function getCalendarEventsProperty(): array
    {
        return $this->baseQuery()
            ->orderBy('date')
            ->orderBy('heure')
            ->limit(500)
            ->get()
            ->map(function (Booking $rdv) {
                $start = Carbon::parse($rdv->date->format('Y-m-d').' '.($rdv->heure ?: '09:00:00'));
                $end = (clone $start)->addMinutes((int) ($rdv->duree ?: $rdv->duree_estimee ?: 60));
                $client = $rdv->client?->name ?: 'Client';
                $service = $rdv->service_display_name ?: 'Service';
                $employe = $rdv->employe?->name ?: 'Non assigné';
                $zone = $rdv->serviceZone?->name ?: 'Zone non définie';

                return [
                    'id' => $rdv->id,
                    'title' => $service.' • '.$client,
                    'start' => $start->toIso8601String(),
                    'end' => $end->toIso8601String(),
                    'backgroundColor' => $this->eventColor((string) $rdv->status),
                    'borderColor' => $this->eventColor((string) $rdv->status),
                    'extendedProps' => [
                        'status' => $rdv->status,
                        'client' => $client,
                        'employe' => $employe,
                        'zone' => $zone,
                        'adresse' => $rdv->location_display,
                        'reference' => $rdv->booking_reference,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    public function getUpcomingProperty()
    {
        return $this->baseQuery()
            ->whereDate('date', '>=', today())
            ->orderBy('date')
            ->orderBy('heure')
            ->limit(12)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.admin.calendrier-interne', [
            'zones' => $this->zones,
            'services' => $this->services,
            'employes' => $this->employes,
            'stats' => $this->stats,
            'calendarEvents' => $this->calendarEvents,
            'upcoming' => $this->upcoming,
        ]);
    }
}
