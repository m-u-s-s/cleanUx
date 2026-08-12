<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\User;

class CommissionService
{
    private const MINIMUM_COMMISSION = 200;  // €2.00 minimum in cents

    private function platformRate(): float
    {
        return ((int) config('brio.platform_fee_percent', 15)) / 100;
    }

    private function useNegotiatedCommission(): bool
    {
        return (bool) config('brio.use_negotiated_commission', false);
    }

    /**
     * Single source of truth for the platform-fee / provider-payout split.
     *
     * Décision produit 2026-06-11 : commission = TAUX UNIQUE au lancement.
     * Le taux négocié par prestataire (ProviderProfile.commission_rate) n'est appliqué
     * que si brio.use_negotiated_commission est activé (off par défaut). Cela garantit
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
    /**
     * LE MÊME PARTAGE, POUR UN MONTANT QUI N'EST PAS CELUI D'UN DEVIS.
     *
     * Un supplément proposé sur place n'est pas une réservation : il n'a ni `devis_estime` ni
     * colonne de paiement. Il doit pourtant supporter EXACTEMENT la même commission, sinon il
     * échapperait à la retenue de la plateforme et le portefeuille interne du prestataire
     * divergerait de ce que Stripe lui a réellement transféré — le genre d'écart qu'on ne découvre
     * qu'au rapprochement comptable, des mois plus tard.
     *
     * @param  User|null  $provider  pour son éventuel taux négocié
     * @return array{
     *   total_cents: int,
     *   platform_fee_cents: int,
     *   provider_payout_cents: int,
     *   commission_rate: float,
     *   currency: string
     * }
     */
    public function calculateForAmount(int $totalCents, ?User $provider = null): array
    {
        $totalCents = max(0, $totalCents);

        $negotiatedRate = $provider?->providerProfile?->commission_rate;

        $commissionRate = ($this->useNegotiatedCommission() && $negotiatedRate !== null)
            ? (float) $negotiatedRate
            : $this->platformRate();

        $platformFeeCents = max(
            (int) round($totalCents * $commissionRate),
            self::MINIMUM_COMMISSION
        );

        // La commission ne dépasse jamais le total : sur un petit supplément, le minimum de 2 €
        // absorberait sinon plus que le montant, et le prestataire recevrait un solde négatif.
        $platformFeeCents = min($platformFeeCents, $totalCents);

        return [
            'total_cents' => $totalCents,
            'platform_fee_cents' => $platformFeeCents,
            'provider_payout_cents' => $totalCents - $platformFeeCents,
            'commission_rate' => $commissionRate,
            'currency' => 'eur',
        ];
    }

    /**
     * Le partage commission / reversement pour une réservation.
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
