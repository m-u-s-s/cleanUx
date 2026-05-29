<?php

namespace App\Models\Concerns;

/**
 * HasBookingPricing
 *
 * Pricing-related accessors, formatters, and helpers for the Booking model.
 * Extracted from Booking to keep the model file under 400 lines.
 *
 * Pricing columns on the bookings table:
 *   - estimated_price       (decimal:2) — quote at time of booking
 *   - devis_estime          (decimal:2) — legacy FR alias for estimated_price
 *   - payment_amount_cents  (integer)   — Stripe captured amount in cents
 *   - provider_amount_cents (integer)   — provider share in cents
 *   - platform_fee_cents    (integer)   — platform fee in cents
 *   - currency              (string)    — ISO 4217 code (default EUR)
 */
trait HasBookingPricing
{
    /**
     * Final captured amount in major currency units (e.g. EUR).
     * Returns null when payment has not been captured yet.
     */
    public function getFinalPriceAttribute(): ?float
    {
        if ($this->payment_amount_cents === null) {
            return null;
        }

        return round($this->payment_amount_cents / 100, 2);
    }

    /**
     * Platform commission in major currency units.
     */
    public function getPlatformFeeAttribute(): ?float
    {
        if ($this->platform_fee_cents === null) {
            return null;
        }

        return round($this->platform_fee_cents / 100, 2);
    }

    /**
     * Provider net payout in major currency units.
     */
    public function getProviderAmountAttribute(): ?float
    {
        if ($this->provider_amount_cents === null) {
            return null;
        }

        return round($this->provider_amount_cents / 100, 2);
    }

    /**
     * Human-readable price string with currency symbol.
     * Falls back to estimated_price when final price is unavailable.
     *
     * @example "€ 75,00"
     */
    public function getFormattedPriceAttribute(): string
    {
        $amount = $this->getFinalPriceAttribute() ?? (float) ($this->estimated_price ?? $this->devis_estime ?? 0);
        $currency = $this->currency ?? 'EUR';

        return number_format($amount, 2, ',', ' ').' '.$currency;
    }

    /**
     * Whether a payment capture has been registered for this booking.
     */
    public function hasCapturedPayment(): bool
    {
        return $this->payment_amount_cents !== null
            && $this->payment_captured_at !== null;
    }

    /**
     * Effective display price: final (captured) or estimated, whichever is available.
     */
    public function getEffectivePriceAttribute(): ?float
    {
        return $this->getFinalPriceAttribute()
            ?? ($this->estimated_price ? (float) $this->estimated_price : null);
    }
}
