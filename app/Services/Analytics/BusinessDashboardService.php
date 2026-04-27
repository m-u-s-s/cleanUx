<?php

namespace App\Services\Analytics;

use App\Models\CustomerClaim;
use App\Models\Mission;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\Carbon;

class BusinessDashboardService
{
    public function metrics(): array
    {
        $now = now();

        $currentMonthStart = $now->copy()->startOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $currentRevenue = $this->revenueBetween($currentMonthStart, $now);
        $previousRevenue = $this->revenueBetween($previousMonthStart, $previousMonthEnd);

        return [
            'revenue_current_month' => $currentRevenue,
            'revenue_previous_month' => $previousRevenue,
            'revenue_growth' => $this->growth($currentRevenue, $previousRevenue),

            'bookings_current_month' => RendezVous::whereBetween('date', [$currentMonthStart, $now])->count(),
            'missions_completed_current_month' => Mission::where('status', 'completed')
                ->whereBetween('actual_end_at', [$currentMonthStart, $now])
                ->count(),

            'clients_total' => User::whereIn('role', ['client', 'entreprise'])->count(),
            'premium_clients' => User::whereIn('role', ['client', 'entreprise'])
                ->where('plan_type', 'premium')
                ->where('plan_status', 'active')
                ->count(),

            'employees_total' => User::where('role', 'employe')->where('is_active', true)->count(),

            'open_claims' => class_exists(CustomerClaim::class)
                ? CustomerClaim::whereIn('status', ['open', 'in_review', 'waiting_client'])->count()
                : 0,

            'avg_booking_value' => round((float) RendezVous::whereBetween('date', [$currentMonthStart, $now])->avg('devis_estime'), 2),

            'weekly_revenue' => $this->weeklyRevenue(),
        ];
    }

    protected function revenueBetween($from, $to): float
    {
        return round((float) RendezVous::whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirme', 'termine'])
            ->sum('devis_estime'), 2);
    }

    protected function growth(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    protected function weeklyRevenue(): array
    {
        $weeks = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = Carbon::now()->subWeeks($i)->startOfWeek();
            $end = Carbon::now()->subWeeks($i)->endOfWeek();

            $weeks[] = [
                'label' => 'S'.$start->format('W'),
                'revenue' => $this->revenueBetween($start, $end),
                'bookings' => RendezVous::whereBetween('date', [$start, $end])->count(),
            ];
        }

        return $weeks;
    }
}