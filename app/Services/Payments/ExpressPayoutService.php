<?php

namespace App\Services\Payments;

use App\Models\ProviderPayout;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * LE CASH-OUT EXPRESS (E14) — être payé maintenant, contre des frais.
 *
 * LE BESOIN EST RÉEL ET IL EST BRUTAL. Un indépendant qui a travaillé lundi est payé le vendredi
 * suivant, parfois plus tard. Entre les deux il y a un plein d'essence, une facture, une échéance
 * de loyer. La question n'est pas de savoir si des frais sont acceptables — c'est de savoir si on
 * lui laisse le choix, ou s'il va le chercher chez un organisme de crédit à 20 %.
 *
 * LES FRAIS S'AFFICHENT AVANT, EN EUROS, PAS EN POURCENTAGE. « 1,5 % » se lit et ne se comprend
 * pas ; « 2,40 € » se comprend. Le montant NET est calculé et rendu, pour qu'il n'y ait aucune
 * surprise sur le virement.
 *
 * LE PLANCHER EXISTE POUR PROTÉGER DU RATIO, PAS DE LA DÉPENSE. Sous vingt euros, des frais fixes
 * représenteraient une part indécente du montant — on refuse plutôt que de proposer une mauvaise
 * affaire à quelqu'un qui n'a pas le choix.
 *
 * ET LE VIREMENT ORDINAIRE RESTE GRATUIT. L'express est une OPTION, jamais le chemin par défaut :
 * en faire le bouton principal reviendrait à taxer l'impatience de gens qu'on paye en retard.
 */
class ExpressPayoutService
{
    /** La commission, en points de base — 150 = 1,5 %. */
    public const FRAIS_BASIS_POINTS = 150;

    /** Un minimum de frais : sous ce seuil, le coût de traitement dépasse la commission. */
    public const FRAIS_MINIMUM_CENTS = 100;

    /**
     * En dessous, on refuse.
     *
     * PAS POUR PROTÉGER LA PLATEFORME, mais le prestataire : sur dix euros, un euro de frais fait
     * 10 %. On ne propose pas une mauvaise affaire à quelqu'un qui n'a pas le choix.
     */
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

        /*
         * LE SOLDE EST VÉRIFIÉ SUR LE BRUT, pas sur le net. Les frais sont prélevés SUR le
         * virement : quelqu'un qui a exactement 50 € doit pouvoir demander 50 € et en recevoir
         * 49,25 — refuser parce que 50,75 dépasse son solde serait incompréhensible.
         */
        $solde = $this->wallet->balance($prestataire->id, $devise);

        if ($solde['available'] < $montantCents / 100) {
            throw ValidationException::withMessages([
                'amount' => sprintf('Solde insuffisant (disponible : %.2f %s).', $solde['available'], $devise),
            ]);
        }

        // Le retrait ordinaire fait déjà tout le travail — vérification Stripe Connect, ligne de
        // registre, journal d'activité. On ne le réécrit pas : on l'annote.
        $payout = $this->wallet->requestWithdraw($prestataire, $montantCents / 100, $devise);

        $payout->forceFill([
            'metadata' => array_merge((array) $payout->metadata, [
                'source' => 'express_withdraw',
                // Le devis est FIGÉ sur la ligne : relire un taux six mois plus tard donnerait un
                // autre montant que celui qui a été prélevé, et personne ne saurait l'expliquer.
                'express_fee_cents' => $devis['fee_cents'],
                'express_net_cents' => $devis['net_cents'],
                'express_fee_basis_points' => self::FRAIS_BASIS_POINTS,
            ]),
        ])->save();

        Log::info('[wallet] virement express demandé', [
            'provider_user_id' => $prestataire->id,
            'amount_cents' => $montantCents,
            'fee_cents' => $devis['fee_cents'],
        ]);

        return $payout->fresh();
    }
}
