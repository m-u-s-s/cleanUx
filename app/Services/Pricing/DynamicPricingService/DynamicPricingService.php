<?php

namespace App\Services\Pricing\DynamicPricingService;

class DynamicPricingService
{
    public function calculate(float $basePrice, array $context): float
    {
        $multiplier = 1;

        // 📈 demande élevée
        if ($context['demand'] > 7) {
            $multiplier += 0.2;
        }

        // 📉 peu d’employés dispo
        if ($context['supply'] < 3) {
            $multiplier += 0.3;
        }

        // ⚡ ASAP
        if ($context['booking_mode'] === 'asap') {
            $multiplier += 0.25;
        }

        // 📅 week-end
        if (now()->isWeekend()) {
            $multiplier += 0.15;
        }

        return round($basePrice * $multiplier, 2);
    }
}