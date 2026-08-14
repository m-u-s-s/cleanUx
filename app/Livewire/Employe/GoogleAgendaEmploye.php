<?php

namespace App\Livewire\Employe;

use App\Models\Booking;
use App\Models\GoogleCalendarConnection;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GoogleAgendaEmploye extends Component
{
    public function getConnectionProperty(): ?GoogleCalendarConnection
    {
        return GoogleCalendarConnection::query()->where('user_id', auth()->id())->first();
    }

    public function getUpcomingCountProperty(): int
    {
        return Booking::query()
            ->intervenantEst((int) auth()->id())
            ->whereDate('date', '>=', now()->toDateString())
            ->count();
    }

    public function getNextMissionProperty(): ?Booking
    {
        return Booking::query()
            ->with(['serviceZone:id,name', 'serviceCatalog:id,name'])
            ->intervenantEst((int) auth()->id())
            ->whereDate('date', '>=', now()->toDateString())
            ->whereIn('status', ['en_attente', 'confirme', 'en_route', 'sur_place'])
            ->orderBy('date')
            ->orderBy('heure')
            ->first();
    }

    public function render(): View
    {
        return view('livewire.employe.google-agenda-employe', [
            'connection' => $this->connection,
            'upcomingCount' => $this->upcomingCount,
            'nextMission' => $this->nextMission,
        ]);
    }
}
