<?php

namespace App\Livewire\Employe;

use App\Support\Domain\BookingStatus;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class MissionsEmploye extends MesRendezVous
{
    public function getStatsProperty(): array
    {
        $items = $this->rendezVousQuery()
            ->with(['mission', 'serviceZone'])
            ->get();

        return [
            'total' => $items->count(),
            'a_confirmer' => $items->where('status', BookingStatus::EN_ATTENTE)->count(),
            'a_faire' => $items->whereIn('status', [
                BookingStatus::CONFIRME,
                BookingStatus::EN_ROUTE,
                BookingStatus::SUR_PLACE,
            ])->count(),
            'en_route' => $items->where('status', BookingStatus::EN_ROUTE)->count(),
            'sur_place' => $items->where('status', BookingStatus::SUR_PLACE)->count(),
            'terminees' => $items->where('status', BookingStatus::TERMINE)->count(),
            'zone_count' => $items->pluck('service_zone_id')->filter()->unique()->count(),
            'avec_mission' => $items->filter(fn ($rdv) => filled($rdv->mission?->id))->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.employe.missions-employe', [
            'rendezVous' => $this->paginatedRendezVous(),
            'stats' => $this->stats,
            'selectedRendezVous' => $this->selectedRendezVous,
            'selectedMission' => $this->selectedMission,
        ]);
    }
}
