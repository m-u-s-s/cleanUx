<?php

namespace App\Livewire\Employe;

class MissionsEmploye extends MesRendezVous
{
    public function getStatsProperty(): array
    {
        $items = $this->rendezVousQuery()->get();

        return [
            'total' => $items->count(),
            'a_confirmer' => $items->where('status', 'en_attente')->count(),
            'a_faire' => $items->whereIn('status', ['confirme', 'en_route', 'sur_place'])->count(),
            'terminees' => $items->where('status', 'termine')->count(),
            'zone_count' => $items->pluck('service_zone_id')->filter()->unique()->count(),
        ];
    }

    public function render()
    {
        return view('livewire.employe.missions-employe', [
            'rendezVous' => $this->paginatedRendezVous(),
            'stats' => $this->stats,
            'selectedRendezVous' => $this->selectedRendezVous,
            'selectedMission' => $this->selectedMission,
        ])->layout('layouts.app');
    }
}
