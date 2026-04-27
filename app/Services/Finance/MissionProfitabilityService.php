<?php

namespace App\Services\Finance;

use App\Models\Mission;

class MissionProfitabilityService
{
    public function calculate(Mission $mission): array
    {
        $mission->loadMissing(['rendezVous', 'leadEmployee']);

        $price = (float) (
            $mission->rendezVous?->devis_estime
            ?? data_get($mission->rendezVous?->pricing_snapshot, 'devis_estime')
            ?? 0
        );

        $plannedMinutes = $mission->planned_start_at && $mission->planned_end_at
            ? max(0, $mission->planned_start_at->diffInMinutes($mission->planned_end_at))
            : (int) ($mission->rendezVous?->duree_estimee ?? $mission->rendezVous?->duree ?? 90);

        $realMinutes = $mission->actual_start_at && $mission->actual_end_at
            ? max(0, $mission->actual_start_at->diffInMinutes($mission->actual_end_at))
            : null;

        $hourlyEmployeeCost = (float) config('cleanux.finance.default_employee_hourly_cost', 18);
        $travelCost = (float) config('cleanux.finance.default_travel_cost', 8);
        $materialCostRate = (float) config('cleanux.finance.default_material_cost_rate', 0.08);

        $workMinutes = $realMinutes ?? $plannedMinutes;
        $employeeCost = round(($workMinutes / 60) * $hourlyEmployeeCost, 2);
        $materialCost = round($price * $materialCostRate, 2);

        $totalCost = round($employeeCost + $travelCost + $materialCost, 2);
        $grossMargin = round($price - $totalCost, 2);
        $marginRate = $price > 0 ? round(($grossMargin / $price) * 100, 1) : 0;

        return [
            'price' => $price,
            'planned_minutes' => $plannedMinutes,
            'real_minutes' => $realMinutes,
            'work_minutes_used' => $workMinutes,
            'employee_cost' => $employeeCost,
            'travel_cost' => $travelCost,
            'material_cost' => $materialCost,
            'total_cost' => $totalCost,
            'gross_margin' => $grossMargin,
            'margin_rate' => $marginRate,
            'status' => $this->status($marginRate),
        ];
    }

    protected function status(float $marginRate): string
    {
        return match (true) {
            $marginRate >= 45 => 'excellent',
            $marginRate >= 30 => 'good',
            $marginRate >= 15 => 'warning',
            default => 'critical',
        };
    }
}