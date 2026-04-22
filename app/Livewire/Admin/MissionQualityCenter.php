<?php

namespace App\Livewire\Admin;

use App\Models\Mission;
use App\Models\MissionIncident;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class MissionQualityCenter extends Component
{
    use WithPagination;

    public string $incidentStatus = '';
    public string $incidentSeverity = '';
    public string $missionQualityStatus = '';

    public function updating($property): void
    {
        if (in_array($property, ['incidentStatus', 'incidentSeverity', 'missionQualityStatus'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $incidents = MissionIncident::query()
            ->with(['mission.rendezVous', 'reportedBy'])
            ->when($this->incidentStatus !== '', fn ($q) => $q->where('status', $this->incidentStatus))
            ->when($this->incidentSeverity !== '', fn ($q) => $q->where('severity', $this->incidentSeverity))
            ->latest('reported_at')
            ->paginate(10);

        $missions = Mission::query()
            ->with(['rendezVous', 'leadEmployee'])
            ->when($this->missionQualityStatus !== '', fn ($q) => $q->where('quality_status', $this->missionQualityStatus))
            ->latest('id')
            ->limit(12)
            ->get();

        $employeeScores = Mission::query()
            ->join('users', 'users.id', '=', 'missions.lead_employee_id')
            ->whereNotNull('missions.quality_score')
            ->selectRaw('missions.lead_employee_id, users.name as employee_name, AVG(missions.quality_score) as avg_score, COUNT(missions.id) as missions_count')
            ->groupBy('missions.lead_employee_id', 'users.name')
            ->orderByDesc('avg_score')
            ->limit(10)
            ->get();

        $teamScores = DB::table('missions')
            ->join('mission_assignments', 'mission_assignments.mission_id', '=', 'missions.id')
            ->join('rendez_vous', 'rendez_vous.id', '=', 'missions.rendez_vous_id')
            ->whereNotNull('missions.quality_score')
            ->selectRaw('missions.id as mission_id, rendez_vous.booking_reference, AVG(missions.quality_score) as team_score, COUNT(mission_assignments.id) as members_count')
            ->groupBy('missions.id', 'rendez_vous.booking_reference')
            ->havingRaw('COUNT(mission_assignments.id) >= 1')
            ->orderByDesc('team_score')
            ->limit(10)
            ->get();

        return view('livewire.admin.mission-quality-center', [
            'incidents' => $incidents,
            'missions' => $missions,
            'employeeScores' => $employeeScores,
            'teamScores' => $teamScores,
        ]);
    }
}