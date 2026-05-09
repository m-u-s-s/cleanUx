<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PlanningAdmin extends Component
{
    public string $filtreEmploye = '';
    public string $filtreDate = '';
    public string $filtreStatus = '';
    public string $filtrePriorite = '';
    public string $recherche = '';
    public string $semaine = '';

    public function mount(): void
    {
        $this->semaine = now()->startOfWeek()->format('Y-m-d');
    }

    public function updatedFiltreDate(string $value): void
    {
        if ($value !== '') {
            $this->semaine = Carbon::parse($value)->startOfWeek()->format('Y-m-d');
        }
    }

    public function semainePrecedente(): void
    {
        $this->semaine = Carbon::parse($this->semaine)->subWeek()->startOfWeek()->format('Y-m-d');

        if ($this->filtreDate !== '') {
            $this->filtreDate = Carbon::parse($this->semaine)->format('Y-m-d');
        }
    }

    public function semaineSuivante(): void
    {
        $this->semaine = Carbon::parse($this->semaine)->addWeek()->startOfWeek()->format('Y-m-d');

        if ($this->filtreDate !== '') {
            $this->filtreDate = Carbon::parse($this->semaine)->format('Y-m-d');
        }
    }

    public function allerAujourdHui(): void
    {
        $today = now();
        $this->semaine = $today->copy()->startOfWeek()->format('Y-m-d');
        $this->filtreDate = $today->format('Y-m-d');
    }

    public function resetFiltres(): void
    {
        $this->reset([
            'filtreEmploye',
            'filtreDate',
            'filtreStatus',
            'filtrePriorite',
            'recherche',
        ]);

        $this->semaine = now()->startOfWeek()->format('Y-m-d');
    }

    protected function baseQuery(): Builder
    {
        $query = Booking::query()
            ->with(['client', 'employe', 'serviceCatalog', 'serviceZone', 'organizationAccount', 'organizationSite']);

        /** @var User|null $user */
        $user = auth()->user();

        if ($user?->isZoneScopedAdmin() && filled($user->managed_service_zone_id)) {
            $query->where('service_zone_id', $user->managed_service_zone_id);
        }

        return $query
            ->when($this->recherche !== '', fn (Builder $q) => $q->searchStructured($this->recherche))
            ->when($this->filtreEmploye !== '', fn (Builder $q) => $q->where('employe_id', $this->filtreEmploye))
            ->when($this->filtreStatus !== '', fn (Builder $q) => $q->where('status', $this->filtreStatus))
            ->when($this->filtrePriorite !== '', fn (Builder $q) => $q->where('priorite', $this->filtrePriorite));
    }

    protected function planningWindowQuery(): Builder
    {
        $query = $this->baseQuery();

        if ($this->filtreDate !== '') {
            return $query->whereDate('date', $this->filtreDate);
        }

        return $query->whereBetween('date', [
            $this->weekStart()->toDateString(),
            $this->weekEnd()->toDateString(),
        ]);
    }

    protected function focusDayQuery(): Builder
    {
        $focusDate = $this->focusDate()->toDateString();

        return $this->baseQuery()->whereDate('date', $focusDate);
    }

    protected function weekStart(): Carbon
    {
        return Carbon::parse($this->semaine)->startOfWeek();
    }

    protected function weekEnd(): Carbon
    {
        return $this->weekStart()->copy()->endOfWeek();
    }

    protected function focusDate(): Carbon
    {
        return $this->filtreDate !== ''
            ? Carbon::parse($this->filtreDate)
            : now();
    }

    public function getStatsProperty(): array
    {
        $query = $this->planningWindowQuery();
        $rows = $query->get();

        $assignedCount = $rows->filter(fn (Booking $rdv) => filled($rdv->employe_id))->count();
        $totalMinutes = $rows->sum(fn (Booking $rdv) => ($rdv->duree ?? $rdv->duree_estimee ?? 90) + 30);
        $activeCount = $rows->whereIn('status', BookingStatus::active())->count();

        return [
            'total' => $rows->count(),
            'active' => $activeCount,
            'confirme' => $rows->where('status', BookingStatus::CONFIRME)->count(),
            'attente' => $rows->where('status', BookingStatus::EN_ATTENTE)->count(),
            'termine' => $rows->where('status', BookingStatus::TERMINE)->count(),
            'urgentes' => $rows->where('priorite', 'urgente')->count(),
            'sans_employe' => $rows->filter(fn (Booking $rdv) => blank($rdv->employe_id))->count(),
            'assigned_rate' => $rows->count() > 0 ? (int) round(($assignedCount / $rows->count()) * 100) : 0,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
        ];
    }

    public function getInterventionsFocusProperty(): Collection
    {
        return $this->focusDayQuery()
            ->orderByRaw(BookingStatus::employeeDashboardCaseSql('status'))
            ->orderBy('heure')
            ->limit(8)
            ->get();
    }

    public function getPointsAttentionProperty(): Collection
    {
        $now = now();

        return $this->baseQuery()
            ->where(function (Builder $query) use ($now) {
                $query
                    ->where(function (Builder $urgent) {
                        $urgent->where('priorite', 'urgente')
                            ->whereIn('status', [BookingStatus::EN_ATTENTE, BookingStatus::CONFIRME]);
                    })
                    ->orWhere(function (Builder $unassigned) {
                        $unassigned->whereIn('status', BookingStatus::active())
                            ->whereNull('employe_id');
                    })
                    ->orWhere(function (Builder $late) use ($now) {
                        $late->whereIn('status', BookingStatus::active())
                            ->where(function (Builder $dateQuery) use ($now) {
                                $dateQuery
                                    ->whereDate('date', '<', $now->toDateString())
                                    ->orWhere(function (Builder $today) use ($now) {
                                        $today->whereDate('date', $now->toDateString())
                                            ->whereTime('heure', '<', $now->format('H:i:s'));
                                    });
                            });
                    });
            })
            ->orderByDesc('priorite')
            ->orderBy('date')
            ->orderBy('heure')
            ->limit(6)
            ->get();
    }

    public function getChargeEmployesProperty(): Collection
    {
        $rows = $this->planningWindowQuery()->get()->groupBy('employe_id');

        return $this->employes->map(function (User $employe) use ($rows) {
            $rdvs = $rows->get($employe->id, collect());
            $minutes = $rdvs->sum(fn (Booking $rdv) => ($rdv->duree ?? $rdv->duree_estimee ?? 90) + 30);

            return [
                'employe' => $employe,
                'count' => $rdvs->count(),
                'minutes' => $minutes,
                'hours' => round($minutes / 60, 1),
                'urgent_count' => $rdvs->where('priorite', 'urgente')->count(),
                'active_count' => $rdvs->whereIn('status', BookingStatus::active())->count(),
                'is_busy' => $minutes >= 420,
            ];
        })
            ->filter(fn (array $item) => $item['count'] > 0)
            ->sortByDesc('minutes')
            ->values()
            ->take(6);
    }

    public function getWeekSummaryProperty(): array
    {
        $rows = $this->planningWindowQuery()->get();
        $daysWithWork = $rows->groupBy(fn (Booking $rdv) => optional($rdv->date)->toDateString() ?? (string) $rdv->date)->count();
        $entrepriseCount = $rows->filter(fn (Booking $rdv) => filled($rdv->organization_account_id))->count();

        return [
            'days_with_work' => $daysWithWork,
            'entreprise_count' => $entrepriseCount,
            'window_label' => $this->filtreDate !== ''
                ? 'Vue journée ciblée'
                : sprintf('Semaine du %s au %s', $this->weekStart()->format('d/m'), $this->weekEnd()->format('d/m')),
        ];
    }

    public function getEmployesProperty(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_EMPLOYE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getStatusOptionsProperty(): array
    {
        return [
            BookingStatus::EN_ATTENTE => 'En attente',
            BookingStatus::CONFIRME => 'Confirmé',
            BookingStatus::EN_ROUTE => 'En route',
            BookingStatus::SUR_PLACE => 'Sur place',
            BookingStatus::TERMINE => 'Terminé',
            BookingStatus::REFUSE => 'Refusé',
        ];
    }

    public function getPriorityOptionsProperty(): array
    {
        return [
            'normale' => 'Normale',
            'haute' => 'Haute',
            'urgente' => 'Urgente',
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.planning-admin', [
            'stats' => $this->stats,
            'employes' => $this->employes,
            'statusOptions' => $this->statusOptions,
            'priorityOptions' => $this->priorityOptions,
            'interventionsFocus' => $this->interventionsFocus,
            'pointsAttention' => $this->pointsAttention,
            'chargeEmployes' => $this->chargeEmployes,
            'weekSummary' => $this->weekSummary,
            'weekStart' => $this->weekStart(),
            'weekEnd' => $this->weekEnd(),
            'focusDate' => $this->focusDate(),
        ]);
    }
}
