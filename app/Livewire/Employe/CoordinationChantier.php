<?php

namespace App\Livewire\Employe;

use App\Models\FieldTeamMember;
use App\Models\MissionBatch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CoordinationChantier extends Component
{
    public function getLeadBatchesProperty()
    {
        if (! class_exists(FieldTeamMember::class)) {
            return collect();
        }

        $teamIds = FieldTeamMember::query()
            ->where('user_id', auth()->id())
            ->where('is_team_lead', true)
            ->pluck('field_team_id');

        return MissionBatch::with(['days.segments', 'organizationAccount', 'organizationSite'])
            ->whereIn('field_team_id', $teamIds)
            ->active()
            ->latest()
            ->get();
    }

    public function render(): View
    {
        return view('livewire.employe.coordination-chantier', [
            'leadBatches' => $this->leadBatches,
        ]);
    }
}
