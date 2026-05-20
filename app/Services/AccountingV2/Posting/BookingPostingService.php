<?php

namespace App\Services\AccountingV2\Posting;

use App\Models\Booking;
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
                'label' => 'Facturation booking #' . $booking->id,
                'counterparty_type' => 'client',
                'counterparty_id' => $booking->user_id,
            ],
            [
                'account_code' => $this->chart->salesAccount('booking'),
                'credit_cents' => $htCents,
                'label' => 'Vente booking #' . $booking->id,
                'vat_rate' => $rate,
                'vat_amount_cents' => $vatCents,
            ],
        ];
        if ($vatCents > 0) {
            $lines[] = [
                'account_code' => $this->chart->vatCollected(),
                'credit_cents' => $vatCents,
                'label' => 'TVA collectée booking #' . $booking->id,
                'vat_rate' => $rate,
            ];
        }

        return $this->accounting->postIdempotent('Booking', (int) $booking->id, $lines, [
            'journal_code' => 'VEN',
            'reference' => 'BOOK-' . $booking->id,
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
                'label' => 'Encaissement booking #' . $booking->id,
            ],
            [
                'account_code' => $this->chart->clientAccount(),
                'credit_cents' => $ttcCents,
                'label' => 'Lettrage booking #' . $booking->id,
                'counterparty_type' => 'client',
                'counterparty_id' => $booking->user_id,
            ],
        ];
        if ($stripeFeeCents > 0) {
            $lines[] = [
                'account_code' => $this->chart->stripeFeesAccount(),
                'debit_cents' => $stripeFeeCents,
                'label' => 'Frais Stripe booking #' . $booking->id,
            ];
        }
        return $this->accounting->postIdempotent('Booking.payment', (int) $booking->id, $lines, [
            'journal_code' => 'BANK',
            'reference' => 'BOOK-' . $booking->id,
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
                'label' => 'Refund booking #' . $booking->id,
            ],
            [
                'account_code' => $this->chart->bankAccount('stripe'),
                'credit_cents' => $refundCents,
                'label' => 'Refund Stripe booking #' . $booking->id,
            ],
        ];
        return $this->accounting->postIdempotent('Booking.refund', (int) $booking->id, $lines, [
            'journal_code' => 'BANK',
            'reference' => 'REFUND-BOOK-' . $booking->id,
        ]);
    }
}
