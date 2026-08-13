<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionRouteTracking extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        abort_unless($mission->exists, 404);

        // Une affectation révoquée n'ouvre plus rien — voir `Mission::estIntervenant()`.
        $isAssigned = $mission->estIntervenant(Auth::id());

        abort_unless($isAssigned, 403);

        $this->mission = $mission->load(['activeTrackingSession']);
    }

    public function render()
    {
        $this->mission = $this->mission->fresh(['activeTrackingSession']);

        return view('livewire.employe.mission-route-tracking');
    }
}
