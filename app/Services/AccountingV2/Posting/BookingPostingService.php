<?php

namespace App\Services\AccountingV2\Posting;

use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\BookingTip;
use App\Services\AccountingV2\AccountingService;
use App\Services\AccountingV2\ChartOfAccounts;

/** Translate booking events → entries comptables. */
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
                'counterparty_id' => $this->clientDeLaReservation($booking),
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
                'counterparty_id' => $this->clientDeLaReservation($booking),
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
     * @param  int  $providerShareCents  Part réellement transférée au prestataire, 0 si la
     *                                   plateforme garde tout. Se déduit de la charge, jamais
     *                                   d'une hypothèse.
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

        // CHAQUE LIGNE EST CONDITIONNELLE, Y COMPRIS LA BANQUE.
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

        // LA CLÉ D'IDEMPOTENCE EST PROPRE À CE FLUX.
        return $this->accounting->postIdempotent('Booking.cancellation_fee', (int) $booking->id, $lines, [
            'journal_code' => 'VEN',
            'reference' => 'CANCELFEE-'.$booking->id,
            'currency' => $this->deviseDe($booking),
        ]);
    }

    /** Audit MEDIUM — règlement modèle AGENT : à l'encaissement, seule la commission est un produit ; la part prestataire est une dette (467) jusqu'au payout ; TVA sur la seule commission. */
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

    /** Audit MEDIUM — pourboire : encaissé pour le compte du prestataire, c'est une dette (pas un produit), réglée au payout. */
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

    /** Audit MEDIUM — prime d'assurance : Brio revend une police tierce (Mock/ Hiscox/Wakam). */
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

    /** LA DEVISE DE L'ECRITURE EST CELLE DE LA RESERVATION, PLUS `EUR` PAR DEFAUT. */
    /** LE CLIENT DE LA RÉSERVATION — CONTREPARTIE DE L'ÉCRITURE COMPTABLE. */
    private function clientDeLaReservation(Booking $booking): ?int
    {
        $id = $booking->customer_user_id ?? $booking->client_id;

        return $id === null ? null : (int) $id;
    }

    private function deviseDe(?Booking $booking): string
    {
        $devise = $booking === null ? '' : trim((string) $booking->currency);

        return $devise !== ''
            ? strtoupper($devise)
            : strtoupper((string) config('fx.base_currency', 'EUR'));
    }
}
