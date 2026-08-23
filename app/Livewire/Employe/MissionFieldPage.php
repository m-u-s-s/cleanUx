<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use Livewire\Attributes\On;
use Livewire\Component;

class MissionFieldPage extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        $this->mission = $this->recharger($mission);
    }

    /** Le statut a bougé dans le bloc d'actions : cette page en dépend pour ouvrir ses volets. */
    #[On('mission-statut-change')]
    public function rafraichirDepuisLeStatut(): void
    {
        $this->mission = $this->recharger($this->mission->fresh() ?? $this->mission);
    }

    private function recharger(Mission $mission): Mission
    {
        return $mission->load([
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
