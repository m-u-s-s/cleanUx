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
        ]);
    }
}
