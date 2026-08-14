<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\User;

class CommissionService
{
    /**
     * LE PLANCHER DE COMMISSION — 2 €, et désormais réglable sans déploiement.
     *
     * Il existe pour une raison réelle : chaque encaissement coûte des frais fixes, et une
     * commission proportionnelle à 15 % sur une prestation de 4 € ne les couvre pas.
     *
     * Mais il a été pensé pour des prestations de 80 à 200 €, où il ne mord jamais. Depuis qu'on
     * vend des COURSES, il mord presque toujours : sur un trajet urbain de 4,81 €, il prélève 2 €,
     * soit 41 % — pendant que le prestataire lit « taux : 20 % » juste à côté. Le chiffre reste
     * défendable, le silence non : c'est une décision commerciale, elle doit pouvoir se changer
     * depuis la configuration au lieu d'attendre une modification du code.
     */
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
     *   effective_commission_rate: float,
     *   minimum_applied: bool,
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
     *   effective_commission_rate: float,
     *   minimum_applied: bool,
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
            $this->minimumCommissionCents()
        );

        // La commission ne dépasse jamais le total : sur un petit supplément, le minimum de 2 €
        // absorberait sinon plus que le montant, et le prestataire recevrait un solde négatif.
        $platformFeeCents = min($platformFeeCents, $totalCents);

        return [
            'total_cents' => $totalCents,
            'platform_fee_cents' => $platformFeeCents,
            'provider_payout_cents' => $totalCents - $platformFeeCents,
            /*
             * DEUX TAUX, PARCE QU'IL Y A DEUX QUESTIONS.
             *
             * `commission_rate` est le taux de la GRILLE — celui qu'on annonce, celui qui figure
             * au contrat. `effective_commission_rate` est celui qui a été RETENU sur ce montant-ci.
             * Les deux coïncident presque toujours, et divergent dès que le plancher mord : sur une
             * course de 4,81 €, la grille dit 20 % et la retenue vaut 41 %.
             *
             * Ne publier que le premier revenait à afficher, côte à côte, un taux et un montant qui
             * se contredisent — le prestataire fait la division, et c'est lui qui a raison.
             */
            'commission_rate' => $commissionRate,
            'effective_commission_rate' => $totalCents > 0
                ? round($platformFeeCents / $totalCents, 4)
                : 0.0,
            'minimum_applied' => $totalCents > 0
                && $platformFeeCents > (int) round($totalCents * $commissionRate),
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
     *   effective_commission_rate: float,
     *   minimum_applied: bool,
     *   currency: string
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

        /*
         * LA MÊME RÈGLE, APPELÉE — PAS RECOPIÉE.
         *
         * Les deux méthodes calculaient chacune leur partage, à l'identique. Un plancher rendu
         * configurable ici et oublié là aurait donné deux commissions différentes pour un même
         * montant, l'une sur la réservation et l'autre sur le supplément de la même intervention.
         */
        return $this->calculateForAmount($totalCents, $provider);
    }
}
