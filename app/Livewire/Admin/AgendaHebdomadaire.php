<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class AgendaHebdomadaire extends Component
{
    #[Reactive]
    public string $semaine = '';

    #[Reactive]
    public string $employeId = '';

    #[Reactive]
    public string $status = '';

    #[Reactive]
    public string $priorite = '';

    #[Reactive]
    public string $recherche = '';

    #[Reactive]
    public string $focusDate = '';

    protected function weekStart(): Carbon
    {
        return $this->semaine !== ''
            ? Carbon::parse($this->semaine)->startOfWeek()
            : now()->startOfWeek();
    }

    protected function weekEnd(): Carbon
    {
        return $this->weekStart()->copy()->endOfWeek();
    }

    public function render()
    {
        $start = $this->weekStart();
        $end = $this->weekEnd();

        $rdvs = Booking::with(['employe', 'client', 'serviceCatalog', 'serviceZone', 'organizationAccount', 'organizationSite'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($this->employeId !== '', fn ($q) => $q->where('employe_id', $this->employeId))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->priorite !== '', fn ($q) => $q->where('priorite', $this->priorite))
            ->when($this->recherche !== '', fn ($q) => $q->searchStructured($this->recherche))
            ->orderBy('date')
            ->orderBy('heure')
            ->get();

        /** @var User|null $user */
        $user = auth()->user();

        if ($user?->isZoneScopedAdmin() && filled($user->managed_service_zone_id)) {
            $rdvs = $rdvs->where('service_zone_id', (int) $user->managed_service_zone_id)->values();
        }

        $rdvsGrouped = $rdvs->groupBy(fn (Booking $rdv) => optional($rdv->date)->toDateString() ?? (string) $rdv->date);
        $focusDate = $this->focusDate !== '' ? Carbon::parse($this->focusDate)->toDateString() : now()->toDateString();

        $jours = collect();

        foreach (range(0, 6) as $i) {
            $jour = $start->copy()->addDays($i);
            /** @var Collection<int, RendezVous> $rdvsJour */
            $rdvsJour = $rdvsGrouped->get($jour->toDateString(), collect());
            $totalMinutes = $rdvsJour->sum(fn (Booking $rdv) => ($rdv->duree ?? $rdv->duree_estimee ?? 90) + 30);

            $jours->push([
                'label' => $jour->translatedFormat('l d/m'),
                'short_label' => $jour->translatedFormat('D d/m'),
                'date' => $jour->toDateString(),
                'is_focus' => $jour->toDateString() === $focusDate,
                'is_today' => $jour->isToday(),
                'rdvs' => $rdvsJour,
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 1),
                'active_count' => $rdvsJour->whereIn('status', BookingStatus::active())->count(),
                'urgent_count' => $rdvsJour->where('priorite', 'urgente')->count(),
                'unassigned_count' => $rdvsJour->filter(fn (Booking $rdv) => blank($rdv->employe_id))->count(),
            ]);
        }

        return view('livewire.admin.agenda-hebdomadaire', [
            'jours' => $jours,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
