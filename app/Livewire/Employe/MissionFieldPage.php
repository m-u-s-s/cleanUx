<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use Livewire\Component;

class MissionFieldPage extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        $this->mission = $mission->load([
            'booking.client',
            'leadEmployee',
            'checklists.items',
            'media',
        ]);
    }

    public function render()
    {
        return view('livewire.employe.mission-field-page');
    }
}
