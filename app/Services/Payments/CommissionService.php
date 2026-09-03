<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\User;
use App\Services\Commission\ContexteDeCommission;
use App\Services\Commission\ResolveurDeCommission;

class CommissionService
{
    /** LE PLANCHER DE COMMISSION — 2 €, et désormais réglable sans déploiement. */
    private function minimumCommissionCents(): int
    {
        return max(0, (int) config('brio.minimum_commission_cents', 200));
    }

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
     * @return array{
     * total_cents: int,
     * platform_fee_cents: int,
     * provider_payout_cents: int,
     * commission_rate: float,
     * effective_commission_rate: float,
     * minimum_applied: bool,
     * currency: string,
     * commission_rule_id: int|null,
     * commission_origin: string
     * }
     */
    /**
     * LE MÊME PARTAGE, POUR UN MONTANT QUI N'EST PAS CELUI D'UN DEVIS.
     *
     * @param  User|null  $provider  pour son éventuel taux négocié
     * @param  string|null  $currency  La devise de la reservation. `null` retombe sur la devise de
     *                                 BASE de la plateforme -- jamais sur un « eur » ecrit ici.
     * @param  float|null  $tauxImpose  Le taux d'un autre metier — la location entre membres ne
     *                                  se commissionne pas comme une prestation. Il court-circuite
     *                                  le taux plateforme ET le taux negocie du prestataire.
     * @return array{
     * total_cents: int,
     * platform_fee_cents: int,
     * provider_payout_cents: int,
     * commission_rate: float,
     * effective_commission_rate: float,
     * minimum_applied: bool,
     * currency: string,
     * commission_rule_id: int|null,
     * commission_origin: string
     * }
     */
    public function calculateForAmount(
        int $totalCents,
        ?User $provider = null,
        ?string $currency = null,
        ?float $tauxImpose = null,
        ?ContexteDeCommission $contexte = null,
    ): array {
        $totalCents = max(0, $totalCents);

        $negotiatedRate = $provider?->providerProfile?->commission_rate;

        // LA REGLE REGLEE PAR LE SUPER-ADMINISTRATEUR, quand il y en a une. Sans contexte, ou
        // sans regle qui couvre le cas, le taux d'avant s'applique a la virgule pres : poser
        // ce socle ne change le prix de personne tant que rien n'est regle.
        $reglee = $contexte === null ? null : app(ResolveurDeCommission::class)->pour($contexte);

        $commissionRate = match (true) {
            $tauxImpose !== null => max(0.0, min(1.0, $tauxImpose)),
            $reglee?->regle !== null => $reglee->taux,
            $this->useNegotiatedCommission() && $negotiatedRate !== null => (float) $negotiatedRate,
            default => $this->platformRate(),
        };

        // LE PLANCHER SUIT LA REGLE. Sans cela, un taux regle a 0 % prelverait quand meme
        // deux euros, et « gratuit » ne serait jamais gratuit.
        $plancher = $reglee?->regle !== null ? $reglee->planchercents : $this->minimumCommissionCents();

        $platformFeeCents = max(
            (int) round($totalCents * $commissionRate),
            $plancher
        );

        // La commission ne dépasse jamais le total : sur un petit supplément, le minimum de 2 €
        // absorberait sinon plus que le montant, et le prestataire recevrait un solde négatif.
        $platformFeeCents = min($platformFeeCents, $totalCents);

        return [
            'total_cents' => $totalCents,
            'platform_fee_cents' => $platformFeeCents,
            'provider_payout_cents' => $totalCents - $platformFeeCents,
            // DEUX TAUX, PARCE QU'IL Y A DEUX QUESTIONS.
            'commission_rate' => $commissionRate,
            'effective_commission_rate' => $totalCents > 0
                ? round($platformFeeCents / $totalCents, 4)
                : 0.0,
            'minimum_applied' => $totalCents > 0
                && $platformFeeCents > (int) round($totalCents * $commissionRate),
            // LA REGLE QUI A DECIDE. Sans elle, expliquer six mois plus tard pourquoi cette
            // mission a paye 8 % demande de rejouer l'etat de la table a la date du devis.
            'commission_rule_id' => $tauxImpose === null ? $reglee?->regle?->id : null,
            'commission_origin' => $tauxImpose !== null
                ? 'taux impose par le module'
                : ($reglee === null ? 'taux par defaut de la plateforme' : $reglee->origine),
            // LA DEVISE VIENT DE LA RESERVATION, PAS D'UNE CONSTANTE.
            'currency' => strtolower($currency ?: (string) config('fx.base_currency', 'EUR')),
        ];
    }

    /**
     * Le partage commission / reversement pour une réservation.
     *
     * @return array{
     * total_cents: int,
     * platform_fee_cents: int,
     * provider_payout_cents: int,
     * commission_rate: float,
     * effective_commission_rate: float,
     * minimum_applied: bool,
     * currency: string,
     * commission_rule_id: int|null,
     * commission_origin: string
     * }
     */
    public function calculateForBooking(Booking $booking): array
    {
        // Support both modern (estimated_price) and legacy (devis_estime) columns
        $totalCents = (int) round(
            (float) ($booking->devis_estime ?? $booking->estimated_price ?? 0) * 100
        );

        if ($totalCents === 0) {
            $totalCents = (int) ($booking->payment_amount_cents ?? 0);
        }

        // Resolve provider — supports both modern assigned_provider_user_id and legacy employe_id
        $provider = $booking->assignedProvider
            ?? $booking->employe
            ?? $booking->provider
            ?? null;

        // LA MÊME RÈGLE, APPELÉE — PAS RECOPIÉE. Le metier et la zone viennent de la
        // reservation : les deviner ailleurs les ferait diverger au premier ecran de plus.
        return $this->calculateForAmount(
            $totalCents,
            $provider,
            $booking->currency,
            null,
            ContexteDeCommission::pourUneReservation($booking),
        );
    }
}
