<?php

namespace App\Services\AccountingV2\Posting;

use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\BookingTip;
use App\Services\AccountingV2\AccountingService;
use App\Services\AccountingV2\ChartOfAccounts;

/**
 * Translate booking events → entries comptables.
 * Convention :
 *   Booking facturé (payé client) :
 *     411 Clients         Débit  TTC
 *     701 Ventes              Crédit  HT
 *     4457 TVA collectée      Crédit  TVA
 *
 *   Encaissement Stripe :
 *     512100 Banque Stripe Débit  Net (TTC - frais Stripe)
 *     627 Frais Stripe     Débit  Fees
 *     411 Clients             Crédit TTC
 */
class BookingPostingService
{
    public function __construct(
        protected AccountingService $accounting,
        protected ChartOfAccounts $chart,
    ) {}

    public function postBookingSale(Booking $booking, int $ttcCents, float $vatRate, ?int $stripeFeeCents = null): ?string
    {
        if ($ttcCents <= 0) {
            return null;
        }
        $rate = max(0.0, $vatRate);
        $htCents = $rate > 0
            ? (int) round($ttcCents / (1 + ($rate / 100)))
            : $ttcCents;
        $vatCents = $ttcCents - $htCents;

        $lines = [
            [
                'account_code' => $this->chart->clientAccount(),
                'debit_cents' => $ttcCents,
                'label' => 'Facturation booking #'.$booking->id,
                'counterparty_type' => 'client',
                'counterparty_id' => $booking->user_id,
            ],
            [
                'account_code' => $this->chart->salesAccount('booking'),
                'credit_cents' => $htCents,
                'label' => 'Vente booking #'.$booking->id,
                'vat_rate' => $rate,
                'vat_amount_cents' => $vatCents,
            ],
        ];
        if ($vatCents > 0) {
            $lines[] = [
                'account_code' => $this->chart->vatCollected(),
                'credit_cents' => $vatCents,
                'label' => 'TVA collectée booking #'.$booking->id,
                'vat_rate' => $rate,
            ];
        }

        return $this->accounting->postIdempotent('Booking', (int) $booking->id, $lines, [
            'journal_code' => 'VEN',
            'reference' => 'BOOK-'.$booking->id,
            'currency' => $this->deviseDe($booking),
        ]);
    }

    public function postBookingPayment(Booking $booking, int $ttcCents, int $stripeFeeCents = 0): ?string
    {
        if ($ttcCents <= 0) {
            return null;
        }
        $netCents = $ttcCents - $stripeFeeCents;

        $lines = [
            [
                'account_code' => $this->chart->bankAccount('stripe'),
                'debit_cents' => $netCents,
                'label' => 'Encaissement booking #'.$booking->id,
            ],
            [
                'account_code' => $this->chart->clientAccount(),
                'credit_cents' => $ttcCents,
                'label' => 'Lettrage booking #'.$booking->id,
                'counterparty_type' => 'client',
                'counterparty_id' => $booking->user_id,
            ],
        ];
        if ($stripeFeeCents > 0) {
            $lines[] = [
                'account_code' => $this->chart->stripeFeesAccount(),
                'debit_cents' => $stripeFeeCents,
                'label' => 'Frais Stripe booking #'.$booking->id,
            ];
        }

        return $this->accounting->postIdempotent('Booking.payment', (int) $booking->id, $lines, [
            'journal_code' => 'BANK',
            'reference' => 'BOOK-'.$booking->id,
            'currency' => $this->deviseDe($booking),
        ]);
    }

    public function postRefund(Booking $booking, int $refundCents): ?string
    {
        if ($refundCents <= 0) {
            return null;
        }
        $lines = [
            [
                'account_code' => $this->chart->refundAccount(),
                'debit_cents' => $refundCents,
                'label' => 'Refund booking #'.$booking->id,
            ],
            [
                'account_code' => $this->chart->bankAccount('stripe'),
                'credit_cents' => $refundCents,
                'label' => 'Refund Stripe booking #'.$booking->id,
            ],
        ];

        return $this->accounting->postIdempotent('Booking.refund', (int) $booking->id, $lines, [
            'journal_code' => 'BANK',
            'reference' => 'REFUND-BOOK-'.$booking->id,
            'currency' => $this->deviseDe($booking),
        ]);
    }

    /**
     * FRAIS D'ANNULATION — un produit encaissé qui n'entrait dans aucun livre.
     *
     * Le compte `708 Produits annexes (frais d'annulation)` était déclaré au plan comptable, et
     * `ChartOfAccounts::salesAccount('cancellation_fee')` le renvoyait déjà. Rien ne l'appelait :
     * on prélevait de l'argent réel sur la carte du client et le grand livre n'en savait rien.
     *
     * CE N'EST PAS UNE VENTE, ET C'EST POURQUOI CE N'EST PAS `postBookingSale`. Aucune prestation
     * n'a été rendue, donc rien à porter en `701`. Il n'y a pas non plus de créance `411` à
     * solder : `BookingAutoPoster::postSale()` n'est déclenché que par `justBecameCompleted()`, une
     * réservation annulée n'a donc jamais généré de facturation client. L'écriture est autonome —
     * la banque au débit, le produit au crédit.
     *
     *   512100 Banque Stripe        Débit   net encaissé (frais − commission Stripe)
     *   627    Frais Stripe         Débit   commission Stripe, s'il y en a
     *   708    Produits annexes         Crédit  frais HT
     *   4457   TVA collectée            Crédit  TVA, si elle est due
     *
     * ── LE PARTAGE SE DÉDUIT, IL NE SE SUPPOSE PAS ───────────────────────────────────────────
     *
     * Le règlement d'annulation ne prévoit aucune part prestataire — ni dans `CancelBookingService`,
     * ni dans la configuration. Mais l'empreinte est une CHARGE À DESTINATION : elle porte
     * `transfer_data.destination` et une `application_fee_amount` calculée sur la commande entière.
     * Ce que Stripe fait de cette commission lors d'une capture PARTIELLE décide où va l'argent, et
     * cela ne s'exerce pas ici : la clé de ce dépôt fait onze caractères, aucune capture réelle n'a
     * jamais eu lieu.
     *
     * Plutôt que de parier, l'écriture LIT le partage réellement appliqué dans la charge, et porte
     * au crédit de `467` ce qui est effectivement parti chez le prestataire. Le livre reste donc
     * vrai quel que soit le comportement de Stripe, et il le DIT — une dette prestataire non nulle
     * sur une annulation se voit au grand livre, là où une supposition codée en dur resterait
     * invisible. La TVA ne porte que sur ce que la plateforme garde, comme pour la commission.
     *
     * @param  int  $providerShareCents  Part réellement transférée au prestataire, 0 si la
     *                                   plateforme garde tout. Se déduit de la charge, jamais
     *                                   d'une hypothèse.
     *
     * ── LA TVA EST UNE QUESTION FISCALE, PAS TECHNIQUE ────────────────────────────────────────
     *
     * Elle n'a pas de réponse évidente et je ne l'invente pas ici. Une indemnité qui répare un
     * préjudice est hors champ ; une somme qui rémunère un droit — celui de réserver un créneau,
     * puis d'y renoncer — y est soumise (CJUE C-250/14, Air France-KLM). Les deux lectures se
     * défendent selon la façon dont les conditions générales qualifient ces frais.
     *
     * Le réglage tranche donc à l'exécution, comme `revenue_model` et `commission_vat_rate` le
     * font déjà pour des questions du même ordre : par défaut on applique le taux du pays de la
     * réservation, c'est-à-dire le traitement d'un produit ordinaire ; poser
     * `ACCOUNTING_CANCELLATION_FEE_VAT_RATE=0` bascule sur « hors champ ». À VALIDER AVEC LE
     * COMPTABLE avant la mise en ligne — c'est une position fiscale, elle engage.
     * @param  int  $feeCents  Montant RÉELLEMENT capturé, TTC.
     */
    public function postCancellationFee(
        Booking $booking,
        int $feeCents,
        int $stripeFeeCents = 0,
        ?float $vatRate = null,
        int $providerShareCents = 0,
    ): ?string {
        if ($feeCents <= 0) {
            return null;
        }

        // Chaque part est bornée par l'encaissement : au-delà, la banque passerait au crédit et
        // l'écriture décrirait une sortie d'argent qui n'a pas eu lieu.
        $stripeFeeCents = max(0, min($stripeFeeCents, $feeCents));
        $providerShareCents = max(0, min($providerShareCents, $feeCents));

        // Ce que la plateforme garde vraiment. Dérivé plutôt que reçu, pour que le total des
        // crédits égale le total des débits quoi qu'on lui passe.
        $platformCents = max(0, $feeCents - $providerShareCents);

        $rate = max(0.0, $vatRate ?? 21.0);
        $htCents = $rate > 0 ? (int) round($platformCents / (1 + ($rate / 100))) : $platformCents;
        $vatCents = $platformCents - $htCents;

        $netCents = $feeCents - $stripeFeeCents;

        $lines = [];

        /*
         * CHAQUE LIGNE EST CONDITIONNELLE, Y COMPRIS LA BANQUE.
         *
         * `AccountingService::post()` REFUSE une ligne à zéro — « Ligne sans montant » — et
         * `BookingAutoPoster` avale l'exception en journalisant. Une ligne nulle ne produirait donc
         * pas une écriture bancale : elle ne produirait AUCUNE écriture, en silence, ce qui est
         * précisément le défaut qu'on répare ici. Le cas existe pour de vrai : sur des frais
         * minuscules, la commission Stripe peut absorber tout l'encaissement.
         */
        if ($netCents > 0) {
            $lines[] = [
                'account_code' => $this->chart->bankAccount('stripe'),
                'debit_cents' => $netCents,
                'label' => 'Encaissement frais d\'annulation booking #'.$booking->id,
            ];
        }

        if ($stripeFeeCents > 0) {
            $lines[] = [
                'account_code' => $this->chart->stripeFeesAccount(),
                'debit_cents' => $stripeFeeCents,
                'label' => 'Frais Stripe sur annulation booking #'.$booking->id,
            ];
        }

        if ($providerShareCents > 0) {
            $lines[] = [
                'account_code' => (string) config('accounting_v2.marketplace.provider_payable_account', '467'),
                'credit_cents' => $providerShareCents,
                'label' => 'Part prestataire sur frais d\'annulation booking #'.$booking->id,
            ];
        }

        if ($htCents > 0) {
            $lines[] = [
                'account_code' => $this->chart->salesAccount('cancellation_fee'),
                'credit_cents' => $htCents,
                'label' => 'Frais d\'annulation booking #'.$booking->id,
                'vat_rate' => $rate,
                'vat_amount_cents' => $vatCents,
            ];
        }

        if ($vatCents > 0) {
            $lines[] = [
                'account_code' => $this->chart->vatCollected(),
                'credit_cents' => $vatCents,
                'label' => 'TVA frais d\'annulation booking #'.$booking->id,
                'vat_rate' => $rate,
            ];
        }

        /*
         * LA CLÉ D'IDEMPOTENCE EST PROPRE À CE FLUX.
         *
         * Elle ne peut pas être `Booking.payment` : une réservation payée puis remboursée n'est pas
         * une réservation dont on a capturé les frais, et partager la clé ferait taire la seconde
         * écriture au motif que la première existe.
         */
        return $this->accounting->postIdempotent('Booking.cancellation_fee', (int) $booking->id, $lines, [
            'journal_code' => 'VEN',
            'reference' => 'CANCELFEE-'.$booking->id,
            'currency' => $this->deviseDe($booking),
        ]);
    }

    /**
     * Audit MEDIUM — règlement modèle AGENT : à l'encaissement, seule la commission
     * est un produit ; la part prestataire est une dette (467) jusqu'au payout ;
     * TVA sur la seule commission.
     *
     *   512100 Banque Stripe   Débit  TTC payé client
     *   467    Dette prestataire   Crédit part prestataire (TTC - commission)
     *   706    Produit commission  Crédit commission HT
     *   4457   TVA collectée       Crédit TVA sur commission
     */
    public function postMarketplaceSettlement(Booking $booking): ?string
    {
        $ttcCents = (int) ($booking->payment_amount_cents
            ?? round(((float) ($booking->devis_estime ?? 0)) * 100));
        if ($ttcCents <= 0) {
            return null;
        }

        $commissionTtc = max(0, (int) ($booking->platform_fee_cents ?? 0));
        // Dérivé pour garantir l'équilibre de l'écriture (TTC = dette + commission).
        $providerCents = max(0, $ttcCents - $commissionTtc);

        $vatRate = max(0.0, (float) config('accounting_v2.marketplace.commission_vat_rate', 21));
        $commissionHt = $vatRate > 0 ? (int) round($commissionTtc / (1 + ($vatRate / 100))) : $commissionTtc;
        $commissionVat = $commissionTtc - $commissionHt;

        $providerPayable = (string) config('accounting_v2.marketplace.provider_payable_account', '467');
        $commissionRevenue = (string) config('accounting_v2.marketplace.commission_revenue_account', '706');

        $lines = [
            [
                'account_code' => $this->chart->bankAccount('stripe'),
                'debit_cents' => $ttcCents,
                'label' => 'Encaissement booking #'.$booking->id,
            ],
            [
                'account_code' => $providerPayable,
                'credit_cents' => $providerCents,
                'label' => 'Dette prestataire booking #'.$booking->id,
            ],
            [
                'account_code' => $commissionRevenue,
                'credit_cents' => $commissionHt,
                'label' => 'Commission marketplace booking #'.$booking->id,
                'vat_rate' => $vatRate,
                'vat_amount_cents' => $commissionVat,
            ],
        ];

        if ($commissionVat > 0) {
            $lines[] = [
                'account_code' => $this->chart->vatCollected(),
                'credit_cents' => $commissionVat,
                'label' => 'TVA commission booking #'.$booking->id,
                'vat_rate' => $vatRate,
            ];
        }

        return $this->accounting->postIdempotent('Booking.settlement', (int) $booking->id, $lines, [
            'journal_code' => 'VEN',
            'reference' => 'SETTLE-'.$booking->id,
            'currency' => $this->deviseDe($booking),
        ]);
    }

    /**
     * Audit MEDIUM — pourboire : encaissé pour le compte du prestataire, c'est une
     * dette (pas un produit), réglée au payout. Pas de TVA (gratification).
     *
     *   512100 Banque Stripe   Débit  montant
     *   467    Dette prestataire   Crédit montant
     */
    public function postTipSettlement(BookingTip $tip): ?string
    {
        $amount = (int) $tip->amount_cents;
        if ($amount <= 0) {
            return null;
        }

        $payable = (string) config('accounting_v2.marketplace.tips_payable_account', '467');

        $lines = [
            [
                'account_code' => $this->chart->bankAccount('stripe'),
                'debit_cents' => $amount,
                'label' => 'Encaissement pourboire #'.$tip->id,
            ],
            [
                'account_code' => $payable,
                'credit_cents' => $amount,
                'label' => 'Dette prestataire (pourboire) #'.$tip->id,
            ],
        ];

        return $this->accounting->postIdempotent('BookingTip', (int) $tip->id, $lines, [
            'journal_code' => 'BANK',
            'reference' => 'TIP-'.$tip->id,
            'currency' => $this->deviseDe($tip->booking),
        ]);
    }

    /**
     * Audit MEDIUM — prime d'assurance : Brio revend une police tierce (Mock/
     * Hiscox/Wakam). La prime encaissée est due à l'assureur = dette fournisseur.
     *
     *   512100 Banque Stripe   Débit  prime
     *   401    Dette assureur      Crédit prime
     */
    public function postInsuranceSettlement(BookingInsurance $insurance): ?string
    {
        $premium = (int) $insurance->premium_cents;
        if ($premium <= 0) {
            return null;
        }

        $payable = (string) config('accounting_v2.marketplace.insurer_payable_account', '401');

        $lines = [
            [
                'account_code' => $this->chart->bankAccount('stripe'),
                'debit_cents' => $premium,
                'label' => 'Encaissement prime assurance #'.$insurance->id,
            ],
            [
                'account_code' => $payable,
                'credit_cents' => $premium,
                'label' => 'Dette assureur (prime) #'.$insurance->id,
            ],
        ];

        return $this->accounting->postIdempotent('BookingInsurance', (int) $insurance->id, $lines, [
            'journal_code' => 'BANK',
            'reference' => 'INSUR-'.$insurance->id,
            'currency' => $this->deviseDe($insurance->booking),
        ]);
    }

    /**
     * LA DEVISE DE L'ECRITURE EST CELLE DE LA RESERVATION, PLUS `EUR` PAR DEFAUT.
     *
     * `AccountingService::post()` retombait sur `'EUR'` faute de recevoir mieux, et AUCUNE des sept
     * methodes de passage ne lui transmettait quoi que ce soit. Une commande en dirhams produisait
     * donc un journal libelle en euros, pour des montants qui n'en etaient pas -- et c'est ce
     * journal qui sert a declarer.
     *
     * `bookings.currency` est NOT NULL et suit desormais la POSITION de la commande, non plus une
     * preference de compte. Le repli sur la devise de base ne sert qu'aux objets sans reservation
     * rattachee, ou a une relation non chargee.
     */
    private function deviseDe(?Booking $booking): string
    {
        $devise = $booking === null ? '' : trim((string) $booking->currency);

        return $devise !== ''
            ? strtoupper($devise)
            : strtoupper((string) config('fx.base_currency', 'EUR'));
    }
}
