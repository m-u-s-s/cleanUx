<?php

namespace App\Services\Analytics;

/** Pure calculation methods for KPI metrics. */
class KpiCalculator
{
    public function cancellationRate(int $total, int $cancelled): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($cancelled / $total) * 100, 1);
    }

    public function completionRate(int $total, int $completed): ?float
    {
        if ($total === 0) {
            return null;
        }

        return round(($completed / $total) * 100, 1);
    }

    /** Percentage change between two periods. */
    public function trend(float $previous, float $current): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function cancellationTrend(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
