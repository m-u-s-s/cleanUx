<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmployeDashboard extends Component
{
    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function todayMissionsQuery()
    {
        return Booking::with(['client', 'serviceZone', 'serviceCatalog', 'postalCode', 'mission'])
            ->where('employe_id', Auth::id())
            ->whereDate('date', today()->toDateString());
    }

    public function getMissionsDuJourProperty(): Collection
    {
        return $this->todayMissionsQuery()
            ->whereIn('status', BookingStatus::employeeDashboard())
            ->orderByRaw(BookingStatus::employeeDashboardCaseSql('status'))
            ->orderBy('heure')
            ->get();
    }

    public function getProchaineMissionProperty(): ?Booking
    {
        return $this->todayMissionsQuery()
            ->whereIn('status', BookingStatus::active())
            ->orderBy('heure')
            ->first();
    }

    public function getHistoriqueRecentProperty(): Collection
    {
        return Booking::with(['client', 'serviceZone', 'serviceCatalog', 'postalCode', 'mission'])
            ->where('employe_id', Auth::id())
            ->where('status', BookingStatus::TERMINE)
            ->latest('mission_finished_at')
            ->latest('date')
            ->limit(5)
            ->get();
    }

    public function getStatsJourProperty(): array
    {
        $missions = $this->missionsDuJour;

        $total = $missions->count();
        $terminees = $missions->where('status', BookingStatus::TERMINE)->count();
        $minutesPrevues = $missions->sum(fn ($rdv) => (int) ($rdv->duree_estimee ?? $rdv->duree ?? 90));

        return [
            'total' => $total,
            'a_faire' => $missions->whereIn('status', [BookingStatus::EN_ATTENTE, BookingStatus::CONFIRME])->count(),
            'en_cours' => $missions->whereIn('status', [BookingStatus::EN_ROUTE, BookingStatus::SUR_PLACE])->count(),
            'terminees' => $terminees,
            'refusees' => $missions->where('status', BookingStatus::REFUSE)->count(),
            'urgentes' => $missions->where('priorite', 'urgente')->count(),
            'minutes_prevues' => $minutesPrevues,
            'heures_prevues' => round($minutesPrevues / 60, 1),
            'progression' => $total > 0 ? (int) round(($terminees / $total) * 100) : 0,
        ];
    }

    public function getAssignedZonesProperty(): Collection
    {
        $user = $this->currentUser();

        if (! $user) {
            return collect();
        }

        return $user->serviceZones()
            ->wherePivot('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getMissionsHorsZoneProperty(): Collection
    {
        $assignedZoneIds = $this->assignedZones
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($assignedZoneIds === []) {
            return collect();
        }

        return $this->missionsDuJour
            ->filter(fn ($rdv) => filled($rdv->service_zone_id) && ! in_array((int) $rdv->service_zone_id, $assignedZoneIds, true))
            ->values();
    }

    public function getUrgencesDuJourProperty(): Collection
    {
        return $this->missionsDuJour
            ->filter(fn ($rdv) => $rdv->priorite === 'urgente' || in_array($rdv->status, [BookingStatus::EN_ATTENTE, BookingStatus::EN_ROUTE, BookingStatus::SUR_PLACE], true))
            ->values()
            ->take(4);
    }

    public function getPaymentStatusProperty(): array
    {
        $user = $this->currentUser();

        $canReceivePayments = $user && method_exists($user, 'canReceiveStripeConnectPayments')
            ? $user->canReceiveStripeConnectPayments()
            : false;

        return [
            'ready' => $canReceivePayments,
            'label' => $canReceivePayments ? 'Paiement actif' : 'Paiement à configurer',
        ];
    }

    public function render(): View
    {
        return view('livewire.employe-dashboard', [
            'missionsDuJour' => $this->missionsDuJour,
            'prochaineMission' => $this->prochaineMission,
            'historiqueRecent' => $this->historiqueRecent,
            'statsJour' => $this->statsJour,
            'assignedZones' => $this->assignedZones,
            'missionsHorsZone' => $this->missionsHorsZone,
            'urgencesDuJour' => $this->urgencesDuJour,
            'paymentStatus' => $this->paymentStatus,
        ]);
    }
}
