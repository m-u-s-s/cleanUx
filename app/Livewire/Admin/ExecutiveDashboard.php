<?php

namespace App\Livewire\Admin;

use App\Models\Mission;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ExecutiveDashboard extends Component
{
    public function render()
    {
        $global = [
            'avg_quality' => Mission::avg('quality_score'),
            'missions' => Mission::count(),
            'incidents' => DB::table('mission_incidents')->count(),
            'revenue' => DB::table('rendez_vous')->sum('devis_estime'),
        ];

        $profitability = DB::table('missions')
            ->join('rendez_vous','rendez_vous.id','=','missions.rendez_vous_id')
            ->selectRaw('DATE(missions.created_at) as date, SUM(devis_estime) as revenue')
            ->groupBy('date')
            ->orderBy('date','desc')
            ->limit(30)
            ->get();

        return view('livewire.admin.executive-dashboard', compact('global','profitability'));
    }
}