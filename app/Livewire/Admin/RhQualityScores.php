<?php

namespace App\Livewire\Admin;

use App\Models\Mission;
use Livewire\Component;

class RhQualityScores extends Component
{
    public function render()
    {
        $employeeScores = Mission::query()
            ->join('users', 'users.id', '=', 'missions.lead_employee_id')
            ->whereNotNull('missions.quality_score')
            ->selectRaw('missions.lead_employee_id, users.name as employee_name, AVG(missions.quality_score) as avg_score, COUNT(missions.id) as missions_count')
            ->groupBy('missions.lead_employee_id', 'users.name')
            ->orderByDesc('avg_score')
            ->limit(20)
            ->get();

        return view('livewire.admin.rh-quality-scores', [
            'employeeScores' => $employeeScores,
        ]);
    }
}