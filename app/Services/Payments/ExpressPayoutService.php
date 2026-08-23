<?php

namespace App\Services\Payments;

use App\Models\ProviderPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** LE CASH-OUT EXPRESS (E14) — être payé maintenant, contre des frais. */
class ExpressPayoutService
{
    /** La commission, en points de base — 150 = 1,5 %. */
    public const FRAIS_BASIS_POINTS = 150;

    /** Un minimum de frais : sous ce seuil, le coût de traitement dépasse la commission. */
    public const FRAIS_MINIMUM_CENTS = 100;

    /** En dessous, on refuse. */
    public const MONTANT_MINIMUM_CENTS = 2000;

    public function __construct(
        protected ProviderWalletService $wallet,
    ) {}

    /**
     * Le devis d'un retrait express — À AFFICHER AVANT le bouton.
     *
     * @return array<string, mixed>
     */
    public function devis(int $montantCents): array
    {
        $frais = max(
            self::FRAIS_MINIMUM_CENTS,
            (int) round($montantCents * self::FRAIS_BASIS_POINTS / 10000),
        );

        return [
            'amount_cents' => $montantCents,
            'fee_cents' => $frais,
            // LE NET, calculé et rendu : c'est le seul chiffre qui compte pour celui qui reçoit.
            'net_cents' => max(0, $montantCents - $frais),
            'fee_basis_points' => self::FRAIS_BASIS_POINTS,
            'minimum_cents' => self::MONTANT_MINIMUM_CENTS,
            'eligible' => $montantCents >= self::MONTANT_MINIMUM_CENTS,
        ];
    }

    /**
     * Demander un virement instantané.
     *
     * @throws ValidationException
     */
    public function demander(User $prestataire, int $montantCents, string $devise = 'EUR'): ProviderPayout
    {
        $devis = $this->devis($montantCents);

        if (! $devis['eligible']) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Le virement instantané demande au moins %s. En dessous, les frais représenteraient une part trop importante.',
                    number_format(self::MONTANT_MINIMUM_CENTS / 100, 2, ',', ' ').' €',
                ),
            ]);
        }

        // LE SOLDE EST VÉRIFIÉ SUR LE BRUT, pas sur le net.
        $solde = $this->wallet->balance($prestataire->id, $devise);

        if ($solde['available'] < $montantCents / 100) {
            throw ValidationException::withMessages([
                'amount' => sprintf('Solde insuffisant (disponible : %.2f %s).', $solde['available'], $devise),
            ]);
        }

        // LE VERSEMENT PORTE LE NET, ET LES FRAIS DEVIENNENT UNE ÉCRITURE.
        return DB::transaction(function () use ($prestataire, $devis, $devise) {
            // Le retrait ordinaire fait déjà tout le travail — vérification Stripe Connect, ligne
            // de registre, journal d'activité. On ne le réécrit pas : on l'annote.
            $payout = $this->wallet->requestWithdraw($prestataire, $devis['net_cents'] / 100, $devise);

            $this->wallet->recordExpressFee($payout, $devis['fee_cents']);

            $payout->forceFill([
                'metadata' => array_merge((array) $payout->metadata, [
                    'source' => 'express_withdraw',
                    // Le devis est FIGÉ sur la ligne : relire un taux six mois plus tard donnerait
                    // un autre montant que celui prélevé, et personne ne saurait l'expliquer.
                    'express_gross_cents' => $devis['amount_cents'],
                    'express_fee_cents' => $devis['fee_cents'],
                    'express_net_cents' => $devis['net_cents'],
                    'express_fee_basis_points' => self::FRAIS_BASIS_POINTS,
                ]),
            ])->save();

            Log::info('[wallet] virement express demandé', [
                'provider_user_id' => $prestataire->id,
                'gross_cents' => $devis['amount_cents'],
                'fee_cents' => $devis['fee_cents'],
                'net_cents' => $devis['net_cents'],
            ]);

            return $payout->fresh();
        });
    }
}
