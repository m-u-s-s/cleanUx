<?php

namespace App\Livewire\Employe;

use App\Models\MissionBatch;
use Livewire\Component;

class CoordinationChantier extends Component
{
    public function getLeadBatchesProperty()
    {
        if (! class_exists(\App\Models\FieldTeamMember::class)) {
            return collect();
        }

        $teamIds = \App\Models\FieldTeamMember::query()
            ->where('user_id', auth()->id())
            ->where('is_team_lead', true)
            ->pluck('field_team_id');

        return MissionBatch::with(['days.segments', 'organizationAccount', 'organizationSite'])
            ->whereIn('field_team_id', $teamIds)
            ->active()
            ->latest()
            ->get();
    }

    public function render()
    {
        return view('livewire.employe.coordination-chantier', [
            'leadBatches' => $this->leadBatches,
        ])->layout('layouts.app');
    }
}
