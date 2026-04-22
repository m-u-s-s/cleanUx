<?php

namespace App\Livewire\Admin;

use App\Models\Mission;
use Livewire\Component;

class MissionHistoryPanel extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        $this->mission = $mission->load([
            'events.actor',
            'incidents.reportedBy',
            'qualityReviews.reviewer',
            'report',
            'media',
            'checklists.items',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.mission-history-panel');
    }
}