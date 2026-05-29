<?php

namespace App\Services\Pricing;

use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use Carbon\Carbon;

/**
 * 7.5 — ML-based dynamic pricing (demand-aware surge pricing).
 *
 * Interface defined; model training deferred.
 *
 * Rationale:
 *   The existing SurgeService computes a static multiplier per zone/time-slot
 *   from real-time provider-online count. This service will replace/complement
 *   it with a predictive model trained on historical demand data.
 *
 * Architecture plan:
 *   - Features: day-of-week, hour, weather, zone demand history (14d),
 *     competing provider count, holiday calendar, event proximity.
 *   - Output: surge_multiplier in [1.0, 2.5] per (zone, trade, slot).
 *   - Training: Python/statsmodels or Prophet job, hourly retraining.
 *   - Inference: cached in Redis with TTL 15min; HTTP microservice fallback.
 *   - Fallback: return null (caller uses SurgeService rule-based multiplier).
 *
 * TODO: train initial model
 * TODO: implement DynamicPricingProvider contract + CachedMlPricingProvider
 * TODO: shadow mode — log ML predictions vs actual booking conversion rate
 * TODO: pricing_ml_predictions table for audit / A-B testing
 */
class DynamicPricingMlService
{
    /**
     * Predict the demand-based surge multiplier for a booking slot.
     *
     * @param  ServiceCatalog  $catalog  The service being booked
     * @param  ServiceZone  $zone  The geographic zone
     * @param  Carbon  $slot  The desired booking start time
     * @return float|null Multiplier in [1.0, 2.5]. Null = model unavailable.
     *
     * TODO: implement
     */
    public function predictSurgeMultiplier(
        ServiceCatalog $catalog,
        ServiceZone $zone,
        Carbon $slot,
    ): ?float {
        return null; // soft-fail — callers use SurgeService as fallback
    }

    /**
     * Batch-predict for a full day of slots (e.g. for calendar UI heatmap).
     *
     * @return array<string, float> Keys are 'H:i' strings, values are multipliers.
     *
     * TODO: implement
     */
    public function predictDay(ServiceCatalog $catalog, ServiceZone $zone, Carbon $date): array
    {
        return []; // TODO: implement
    }
}
