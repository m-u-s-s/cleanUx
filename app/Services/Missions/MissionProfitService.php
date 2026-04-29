<?php

namespace App\Services\Missions;

use App\Models\Mission;

class MissionProfitService
{
    public function calculate(Mission $mission): Mission
    {
        $rdv = $mission->rendezVous;

        $clientPrice = (float) ($rdv->devis_estime ?? 0);

        $durationMinutes = $this->calculateDuration($mission);
        $travelMinutes = $this->calculateTravelTime($mission);

        $hourlyRate = 18; // 💡 à remplacer par DB plus tard
        $employeeCost = ($durationMinutes / 60) * $hourlyRate;

        $margin = $clientPrice - $employeeCost;

        $mission->update([
            'client_price' => $clientPrice,
            'employee_cost' => round($employeeCost, 2),
            'margin' => round($margin, 2),
            'actual_duration_minutes' => $durationMinutes,
            'travel_duration_minutes' => $travelMinutes,
        ]);

        return $mission;
    }

    protected function calculateDuration(Mission $mission): int
    {
        if (! $mission->actual_start_at || ! $mission->actual_end_at) {
            return 0;
        }

        return $mission->actual_start_at->diffInMinutes($mission->actual_end_at);
    }

    protected function calculateTravelTime(Mission $mission): int
    {
        if (! $mission->activeTrackingSession) {
            return 0;
        }

        $distanceMeters = $mission->activeTrackingSession->distance_meters ?? 0;

        $avgSpeed = 30; // km/h
        $distanceKm = $distanceMeters / 1000;

        return (int) round(($distanceKm / $avgSpeed) * 60);
    }
}