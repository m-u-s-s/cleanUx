<?php

namespace App\Services\Pricing\DynamicPricingService;

use App\Models\ServiceZone;
use App\Services\Pricing\SurgePricingEngine;

/**
 * Conservé pour la compatibilité avec le code legacy (booking, devis, estimations) qui injecte encore cette classe.
 *
 * @deprecated depuis Phase 14 — utiliser directement App\Services\Pricing\SurgePricingEngine.
 */
class DynamicPricingService
{
    public function calculate(float $basePrice, array $context): float
    {
        // Délégation vers le moteur Surge si disponible
        if (class_exists(SurgePricingEngine::class)) {
            try {
                $zone = isset($context['service_zone_id'])
                    ? ServiceZone::find($context['service_zone_id'])
                    : null;

                $result = app(SurgePricingEngine::class)->calculate($basePrice, $zone, $context);

                return (float) $result['final_price'];
            } catch (\Throwable $e) {
                // Fallback silencieux sur la logique legacy si le moteur Surge
                // échoue (ex. table pricing_zones_state pas encore migrée).
                report($e);
            }
        }

        return $this->legacyCalculate($basePrice, $context);
    }

    /** Logique historique pré-Phase 14 (4 règles fixes). */
    protected function legacyCalculate(float $basePrice, array $context): float
    {
        $multiplier = 1;

        if (($context['demand'] ?? 0) > 7) {
            $multiplier += 0.2;
        }

        if (($context['supply'] ?? PHP_INT_MAX) < 3) {
            $multiplier += 0.3;
        }

        if (($context['booking_mode'] ?? null) === 'asap') {
            $multiplier += 0.25;
        }

        if (now()->isWeekend()) {
            $multiplier += 0.15;
        }

        return round($basePrice * $multiplier, 2);
    }
}
