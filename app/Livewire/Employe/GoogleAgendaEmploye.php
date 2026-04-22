<?php

namespace App\Livewire\Employe;

use App\Models\GoogleCalendarConnection;
use App\Models\RendezVous;
use Livewire\Component;

class GoogleAgendaEmploye extends Component
{
    public function getConnectionProperty(): ?GoogleCalendarConnection
    {
        return GoogleCalendarConnection::query()->where('user_id', auth()->id())->first();
    }

    public function getUpcomingCountProperty(): int
    {
        return RendezVous::query()
            ->where('employe_id', auth()->id())
            ->whereDate('date', '>=', now()->toDateString())
            ->count();
    }

    public function getNextMissionProperty(): ?RendezVous
    {
        return RendezVous::query()
            ->with(['serviceZone:id,name', 'serviceCatalog:id,name'])
            ->where('employe_id', auth()->id())
            ->whereDate('date', '>=', now()->toDateString())
            ->whereIn('status', ['en_attente', 'confirme', 'en_route', 'sur_place'])
            ->orderBy('date')
            ->orderBy('heure')
            ->first();
    }

    public function render()
    {
        return view('livewire.employe.google-agenda-employe', [
            'connection' => $this->connection,
            'upcomingCount' => $this->upcomingCount,
            'nextMission' => $this->nextMission,
        ])->layout('layouts.app');
    }
}
