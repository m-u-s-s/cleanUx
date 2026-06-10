<?php

namespace App\Services\Payments;

use App\Models\Booking;

class CommissionService
{
    private const MINIMUM_COMMISSION = 200;  // €2.00 minimum in cents

    private function platformRate(): float
    {
        return ((int) config('cleanux.platform_fee_percent', 15)) / 100;
    }

    private function useNegotiatedCommission(): bool
    {
        return (bool) config('cleanux.use_negotiated_commission', false);
    }

    /**
     * Single source of truth for the platform-fee / provider-payout split.
     *
     * Décision produit 2026-06-11 : commission = TAUX UNIQUE au lancement.
     * Le taux négocié par prestataire (ProviderProfile.commission_rate) n'est appliqué
     * que si cleanux.use_negotiated_commission est activé (off par défaut). Cela garantit
     * que ce calcul reste aligné sur le montant réellement prélevé par Stripe
     * (MissionPaymentService::authorize() consomme ce même calcul).
     *
     * Enforces €2 minimum platform fee.
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

        $negotiatedRate = $provider?->providerProfile?->commission_rate;

        $commissionRate = ($this->useNegotiatedCommission() && $negotiatedRate !== null)
            ? (float) $negotiatedRate
            : $this->platformRate();

        $platformFeeCents = max(
            (int) round($totalCents * $commissionRate),
            self::MINIMUM_COMMISSION
        );

        // Never let fee exceed total
        $platformFeeCents = min($platformFeeCents, $totalCents);

        $providerPayoutCents = $totalCents - $platformFeeCents;

        return [
            'total_cents' => $totalCents,
            'platform_fee_cents' => $platformFeeCents,
            'provider_payout_cents' => $providerPayoutCents,
            'commission_rate' => $commissionRate,
            'currency' => 'eur',
        ];
    }
}
