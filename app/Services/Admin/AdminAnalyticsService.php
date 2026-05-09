<?php

namespace App\Services\Admin;

use App\Models\Feedback;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminAnalyticsService
{
    public function overview(): array
    {
        $driver = DB::connection()->getDriverName();

        $monthExpression = $driver === 'sqlite'
            ? "CAST(strftime('%m', date) AS INTEGER)"
            : 'MONTH(date)';

        $yearExpression = $driver === 'sqlite'
            ? "strftime('%Y', date) = ?"
            : 'YEAR(date) = ?';

        $currentYear = (string) now()->year;

        $monthlyRevenueRows = Booking::query()
            ->selectRaw($monthExpression . ' as month, SUM(devis_estime) as total')
            ->whereRaw($yearExpression, [$currentYear])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyMissionRows = Booking::query()
            ->selectRaw($monthExpression . ' as month, COUNT(*) as total')
            ->whereRaw($yearExpression, [$currentYear])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyRevenue = array_fill(1, 12, 0.0);
        $monthlyMissions = array_fill(1, 12, 0);

        foreach ($monthlyRevenueRows as $row) {
            $month = (int) $row->month;

            if ($month >= 1 && $month <= 12) {
                $monthlyRevenue[$month] = (float) $row->total;
            }
        }

        foreach ($monthlyMissionRows as $row) {
            $month = (int) $row->month;

            if ($month >= 1 && $month <= 12) {
                $monthlyMissions[$month] = (int) $row->total;
            }
        }

        $totalRevenue = (float) Booking::query()->sum('devis_estime');

        $totalMargin = 0.0;

        if (Schema::hasColumn('rendez_vous', 'margin')) {
            $totalMargin = (float) Booking::query()->sum('margin');
        } elseif (Schema::hasColumn('rendez_vous', 'marge')) {
            $totalMargin = (float) Booking::query()->sum('marge');
        }

        $missionsCount = Booking::query()->count();

        $completedMissions = Booking::query()
            ->whereIn('status', ['termine', 'terminé', 'completed'])
            ->count();

        $averageRating = 0.0;

        if (Schema::hasTable('feedbacks')) {
            if (Schema::hasColumn('feedbacks', 'note')) {
                $averageRating = (float) Feedback::query()->avg('note');
            } elseif (Schema::hasColumn('feedbacks', 'rating')) {
                $averageRating = (float) Feedback::query()->avg('rating');
            }
        }

        $statusBreakdown = Booking::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $averageTicket = $missionsCount > 0
            ? (float) Booking::query()->avg('devis_estime')
            : 0.0;

        return [
            // Clés attendues par admin-analytics-dashboard.blade.php
            'total_revenue' => $totalRevenue,
            'total_margin' => $totalMargin,
            'missions_count' => $missionsCount,
            'completed_missions' => $completedMissions,
            'average_rating' => round($averageRating, 2),
            'monthly_revenue' => array_values($monthlyRevenue),
            'monthly_missions' => array_values($monthlyMissions),

            // Clés conservées pour compatibilité avec d’autres composants
            'monthlyRevenue' => collect($monthlyRevenue)
                ->map(fn ($total, $month) => [
                    'month' => (int) $month,
                    'total' => (float) $total,
                ])
                ->values()
                ->all(),

            'monthlyMissions' => collect($monthlyMissions)
                ->map(fn ($total, $month) => [
                    'month' => (int) $month,
                    'total' => (int) $total,
                ])
                ->values()
                ->all(),

            'statusBreakdown' => $statusBreakdown,
            'totalRevenue' => $totalRevenue,
            'totalMargin' => $totalMargin,
            'averageTicket' => $averageTicket,
            'totalBookings' => $missionsCount,
        ];
    }
}