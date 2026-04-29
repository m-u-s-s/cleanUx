<?php

namespace App\Services\Admin;

use App\Models\Mission;
use App\Models\RendezVous;
use App\Models\Feedback;
use Illuminate\Support\Carbon;

class AdminAnalyticsService
{
    public function overview(): array
    {
        return [
            'total_revenue' => RendezVous::sum('devis_estime'),
            'total_margin' => Mission::sum('margin'),
            'missions_count' => Mission::count(),
            'completed_missions' => Mission::where('status', 'completed')->count(),
            'average_rating' => round((float) Feedback::avg('note'), 1),
            'monthly_revenue' => $this->monthlyRevenue(),
            'monthly_missions' => $this->monthlyMissions(),
        ];
    }

    protected function monthlyRevenue(): array
    {
        return RendezVous::query()
            ->selectRaw('MONTH(date) as month, SUM(devis_estime) as total')
            ->whereYear('date', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();
    }

    protected function monthlyMissions(): array
    {
        return Mission::query()
            ->selectRaw('MONTH(planned_start_at) as month, COUNT(*) as total')
            ->whereYear('planned_start_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();
    }
}