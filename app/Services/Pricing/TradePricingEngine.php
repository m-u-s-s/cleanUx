<?php

namespace App\Services\Pricing;

use App\Models\ServiceCatalog;
use App\Models\TradeZonePricing;

/**
 * TradePricingEngine — calculates booking price estimates per trade billing model.
 *
 * Supported billing units (Trade.billing_unit or ServiceCatalog.billing_unit):
 *   hourly   — unit_price × duration_hours
 *   per_m2   — unit_price × surface_m2
 *   per_item — unit_price × quantity
 *   fixed    — unit_price × 1
 *
 * Legacy ServiceCatalog::BILLING_UNITS aliases (hour → hourly, sqm → per_m2,
 * flat → fixed) are normalised before dispatch.
 *
 * Zone pricing (TradeZonePricing) overrides the catalog base_price when
 * an active record exists for the (trade, service_zone) pair.
 */
class TradePricingEngine
{
    /** Canonical billing units accepted by resolveQuantity(). */
    private const UNITS = ['hourly', 'per_m2', 'per_item', 'fixed'];

    /** Legacy alias map from ServiceCatalog::BILLING_UNITS. */
    private const UNIT_ALIASES = [
        'hour' => 'hourly',
        'sqm' => 'per_m2',
        'flat' => 'fixed',
        'quote' => 'fixed',
    ];

    /**
     * Estimate the price for a service given optional form answers and zone.
     *
     * Priority order:
     *   1. ServiceCatalog.base_price  — service-specific price set on the catalog entry.
     *   2. TradeZonePricing.base_rate — zone-level override for the trade (in cents).
     *   3. Trade.default_hourly_rate  — trade-wide billing fallback.
     *
     * @param  array<string,mixed>  $formAnswers  Answers from trade booking form (e.g. duration_hours, surface_m2).
     * @return array{billing_unit:string, unit_price:float, quantity:float, surge_multiplier:float, subtotal:float, currency:string, zone_pricing_applied:bool, price_source:string}
     */
    public function estimate(
        ServiceCatalog $service,
        array $formAnswers = [],
        ?int $serviceZoneId = null
    ): array {
        $trade = $service->trade;
        $billingUnit = $this->normaliseUnit(
            $service->billing_unit ?? $trade?->billing_unit ?? 'hourly'
        );

        // 1. Service-catalog base price (service-specific override).
        $serviceCatalogPrice = ($service->base_price !== null && (float) $service->base_price > 0)
            ? (float) $service->base_price
            : null;

        // 2. Zone-level pricing for the trade.
        $zonePricing = $this->resolveZonePricing($trade?->id, $serviceZoneId);

        // 3. Trade-wide default rate.
        $tradeDefaultPrice = $trade?->default_hourly_rate !== null
            ? (float) $trade->default_hourly_rate
            : null;

        // Resolve unit price following the priority chain.
        [$unitPrice, $priceSource] = match (true) {
            $serviceCatalogPrice !== null => [$serviceCatalogPrice,                        'service_catalog'],
            $zonePricing !== null => [$zonePricing->base_rate_cents / 100,         'zone_pricing'],
            $tradeDefaultPrice !== null => [$tradeDefaultPrice,                          'trade_default'],
            default => [0.0,                                          'none'],
        };

        $basePrice = $unitPrice;

        $surgeMultiplier = (float) ($zonePricing?->surge_multiplier ?? 1.00);
        $quantity = $this->resolveQuantity($billingUnit, $formAnswers);
        $subtotal = $basePrice * $quantity * $surgeMultiplier;

        $subtotal = $this->applyMinMax($subtotal, $zonePricing);

        return [
            'billing_unit' => $billingUnit,
            'unit_price' => round($basePrice, 2),
            'quantity' => $quantity,
            'surge_multiplier' => $surgeMultiplier,
            'subtotal' => round($subtotal, 2),
            'currency' => 'EUR',
            'zone_pricing_applied' => $zonePricing !== null,
            'price_source' => $priceSource,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    protected function normaliseUnit(string $unit): string
    {
        $lower = strtolower(trim($unit));

        return self::UNIT_ALIASES[$lower] ?? (in_array($lower, self::UNITS, true) ? $lower : 'hourly');
    }

    protected function resolveQuantity(string $billingUnit, array $formAnswers): float
    {
        return match ($billingUnit) {
            'hourly' => (float) ($formAnswers['duration_hours'] ?? $formAnswers['nombre_heures'] ?? 2),
            'per_m2' => (float) ($formAnswers['surface_m2'] ?? $formAnswers['surface'] ?? 20),
            'per_item' => (float) ($formAnswers['quantity'] ?? $formAnswers['nombre_items'] ?? 1),
            'fixed' => 1.0,
            default => 1.0,
        };
    }

    protected function resolveZonePricing(?int $tradeId, ?int $serviceZoneId): ?TradeZonePricing
    {
        if (! $tradeId || ! $serviceZoneId) {
            return null;
        }

        return TradeZonePricing::where('trade_id', $tradeId)
            ->where('service_zone_id', $serviceZoneId)
            ->where('is_active', true)
            ->first();
    }

    protected function applyMinMax(float $subtotal, ?TradeZonePricing $zonePricing): float
    {
        if (! $zonePricing) {
            return $subtotal;
        }

        if ($zonePricing->min_price_cents !== null) {
            $subtotal = max($subtotal, $zonePricing->min_price_cents / 100);
        }

        if ($zonePricing->max_price_cents !== null) {
            $subtotal = min($subtotal, $zonePricing->max_price_cents / 100);
        }

        return $subtotal;
    }
}
