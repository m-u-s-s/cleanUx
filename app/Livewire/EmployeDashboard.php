<?php

namespace App\Livewire;

use App\Models\RendezVous;
use App\Support\Domain\BookingStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmployeDashboard extends Component
{
    public function getMissionsDuJourProperty()
    {
        return RendezVous::with(['client', 'serviceZone', 'serviceCatalog', 'postalCode'])
            ->where('employe_id', Auth::id())
            ->whereDate('date', today())
            ->whereIn('status', BookingStatus::employeeDashboard())
            ->orderByRaw(BookingStatus::employeeDashboardCaseSql('status'))
            ->orderBy('heure')
            ->get();
    }

    public function getProchaineMissionProperty()
    {
        return RendezVous::with(['client', 'serviceZone', 'serviceCatalog', 'postalCode'])
            ->where('employe_id', Auth::id())
            ->whereDate('date', today())
            ->whereIn('status', BookingStatus::active())
            ->orderBy('heure')
            ->first();
    }

    public function getHistoriqueRecentProperty()
    {
        return RendezVous::with(['client', 'serviceZone', 'serviceCatalog', 'postalCode'])
            ->where('employe_id', Auth::id())
            ->where('status', BookingStatus::TERMINE)
            ->latest('mission_finished_at')
            ->limit(5)
            ->get();
    }

    public function getStatsJourProperty(): array
    {
        $missions = $this->missionsDuJour;

        return [
            'total' => $missions->count(),
            'a_faire' => $missions->whereIn('status', [BookingStatus::EN_ATTENTE, BookingStatus::CONFIRME])->count(),
            'en_cours' => $missions->whereIn('status', [BookingStatus::EN_ROUTE, BookingStatus::SUR_PLACE])->count(),
            'terminees' => $missions->where('status', BookingStatus::TERMINE)->count(),
            'refusees' => $missions->where('status', BookingStatus::REFUSE)->count(),
        ];
    }

    public function getAssignedZonesProperty(): Collection
    {
        $user = Auth::user();

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
        $assignedZoneIds = $this->assignedZones->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($assignedZoneIds === []) {
            return collect();
        }

        return $this->missionsDuJour
            ->filter(fn ($rdv) => filled($rdv->service_zone_id) && ! in_array((int) $rdv->service_zone_id, $assignedZoneIds, true))
            ->values();
    }

    public function render()
    {
        return view('livewire.employe-dashboard', [
            'missionsDuJour' => $this->missionsDuJour,
            'prochaineMission' => $this->prochaineMission,
            'historiqueRecent' => $this->historiqueRecent,
            'statsJour' => $this->statsJour,
            'assignedZones' => $this->assignedZones,
            'missionsHorsZone' => $this->missionsHorsZone,
        ])->layout('layouts.app');
    }
}
