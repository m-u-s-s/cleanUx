<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\ServiceCatalog;

class BookingIntelligenceService
{
    public function dynamicPrice(float $basePrice, array $context): float
    {
        $multiplier = 1.0;

        if (($context['booking_mode'] ?? 'scheduled') === 'asap') {
            $multiplier += 0.25;
        }

        if (($context['priorite'] ?? 'normale') === 'urgente') {
            $multiplier += 0.20;
        }

        if (now()->isWeekend()) {
            $multiplier += 0.15;
        }

        return round($basePrice * $multiplier, 2);
    }

    public function predictDuration(int $baseMinutes, array $context): int
    {
        $minutes = $baseMinutes;

        if (($context['surface'] ?? null) === 'plus_250') {
            $minutes += 60;
        }

        if (! empty($context['presence_animaux'])) {
            $minutes += 15;
        }

        if (count($context['zones_specifiques'] ?? []) >= 3) {
            $minutes += 20;
        }

        return max(30, $minutes);
    }

    public function suggestAlternativeSlots(ServiceZone $zone, ServiceCatalog $catalog): array
    {
        $suggestions = [];

        $availability = app(EmployeeAvailabilityService::class);

        for ($day = 0; $day < 7; $day++) {
            $date = now()->addDays($day)->format('Y-m-d');

            foreach (['08:00', '10:00', '12:00', '14:00', '16:00'] as $heure) {
                $employee = $availability->resolveBestAvailableEmployeeForSlot(
                    $date,
                    $heure,
                    $zone,
                    $catalog->default_duration_minutes ?? 90
                );

                if ($employee) {
                    $suggestions[] = [
                        'date' => $date,
                        'heure' => $heure,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name,
                    ];
                }

                if (count($suggestions) >= 5) {
                    return $suggestions;
                }
            }
        }

        return $suggestions;
    }
}