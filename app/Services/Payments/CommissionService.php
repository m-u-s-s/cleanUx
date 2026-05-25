<?php

namespace App\Services\Payments;

use App\Models\Booking;

class CommissionService
{
    private const DEFAULT_PLATFORM_RATE = 0.15; // 15% platform commission
    private const MINIMUM_COMMISSION    = 200;  // €2.00 minimum in cents

    /**
     * Calculate the commission breakdown for a given booking.
     *
     * Uses provider-specific rate from ProviderProfile::commission_rate when set,
     * falls back to 15% platform default. Enforces €2 minimum platform fee.
     *
     * @return array{
     *   total_cents: int,
     *   platform_fee_cents: int,
     *   provider_payout_cents: int,
     *   commission_rate: float,
     *   currency: string
     * }
     */
    public function calculateForBooking(Booking $booking): array
    {
        // Support both modern (estimated_price) and legacy (devis_estime) columns
        $totalCents = (int) round(
            (float) ($booking->devis_estime ?? $booking->estimated_price ?? $booking->payment_amount_cents / 100 ?? 0) * 100
        );

        // Resolve provider — supports both modern assigned_provider_user_id and legacy employe_id
        $provider = $booking->assignedProvider
            ?? $booking->employe
            ?? $booking->provider
            ?? null;

        $commissionRate = $provider?->providerProfile?->commission_rate !== null
            ? (float) $provider->providerProfile->commission_rate
            : self::DEFAULT_PLATFORM_RATE;

        $platformFeeCents = max(
            (int) round($totalCents * $commissionRate),
            self::MINIMUM_COMMISSION
        );

        // Never let fee exceed total
        $platformFeeCents = min($platformFeeCents, $totalCents);

        $providerPayoutCents = $totalCents - $platformFeeCents;

        return [
            'total_cents'          => $totalCents,
            'platform_fee_cents'   => $platformFeeCents,
            'provider_payout_cents' => $providerPayoutCents,
            'commission_rate'      => $commissionRate,
            'currency'             => 'eur',
        ];
    }
}
