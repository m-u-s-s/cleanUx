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

    
    public function boot(): void
    {
        // Phase 14 — Aliaser l'ancien DynamicPricingService vers le nouveau
        // moteur Surge (signature backward-compatible)
        $this->app->bind(
            \App\Services\Pricing\DynamicPricingService\DynamicPricingService::class,
            function () {
                // Wrapper qui adapte la nouvelle signature
                return new class {
                    public function calculate(float $basePrice, array $context): float
                    {
                        $zone = isset($context['service_zone_id'])
                            ? \App\Models\ServiceZone::find($context['service_zone_id'])
                            : null;

                        $result = app(\App\Services\Pricing\SurgePricingEngine::class)
                            ->calculate($basePrice, $zone, $context);

                        return $result['final_price'];
                    }
                };
            }
        );
    }
}
