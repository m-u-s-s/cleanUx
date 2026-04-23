<?php

namespace App\Livewire\Employe;

use App\Models\MissionTeamAssignment;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class EquipeTerrain extends Component
{
    public function getUserProperty()
    {
        return Auth::user();
    }

    public function getLedTeamsProperty()
    {
        return $this->user->activeLedFieldTeams()
            ->with(['activeMembers.user', 'serviceZone', 'servicePartner', 'organizationAccount'])
            ->orderBy('name')
            ->get();
    }

    public function getMemberTeamsProperty()
    {
        return $this->user->fieldTeams()
            ->with(['teamLead', 'serviceZone', 'servicePartner'])
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('field_team_members.left_at')
                    ->orWhere('field_team_members.left_at', '>', now());
            })
            ->orderBy('field_teams.name')
            ->get();
    }

    public function getActiveTeamAssignmentsProperty()
    {
        $teamIds = collect($this->ledTeams)->pluck('id')
            ->merge(collect($this->memberTeams)->pluck('id'))
            ->unique()
            ->values();

        if ($teamIds->isEmpty()) {
            return collect();
        }

        return MissionTeamAssignment::query()
            ->with(['fieldTeam', 'mission.rendezVous.client', 'mission.organizationAccount', 'mission.organizationSite'])
            ->whereIn('field_team_id', $teamIds)
            ->whereIn('assignment_status', ['assigned', 'accepted', 'started'])
            ->latest('assigned_at')
            ->limit(12)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.employe.equipe-terrain', [
            'ledTeams' => $this->ledTeams,
            'memberTeams' => $this->memberTeams,
            'activeTeamAssignments' => $this->activeTeamAssignments,
        ]);
    }
}
