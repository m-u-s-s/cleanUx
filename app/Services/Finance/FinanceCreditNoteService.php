<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\FinanceCreditNote;
use App\Models\FinanceInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Audit HIGH — émission d'avoirs (credit notes) lors des remboursements. */
class FinanceCreditNoteService
{
    /**
     * Crée (ou retourne) l'avoir correspondant à un remboursement.
     *
     * @param  int  $amountCents  Montant TTC remboursé, en centimes.
     */
    public function createForRefund(
        Booking $booking,
        int $amountCents,
        ?string $stripeRefundId = null,
        ?string $reason = null,
    ): FinanceCreditNote {
        if ($stripeRefundId) {
            $existing = FinanceCreditNote::query()
                ->where('stripe_refund_id', $stripeRefundId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($booking, $amountCents, $stripeRefundId, $reason) {
            $amount = round($amountCents / 100, 2);
            $taxRate = (float) data_get($booking->pricing_snapshot, 'tax_rate', 0);
            $ht = $taxRate > 0 ? round($amount / (1 + $taxRate / 100), 2) : $amount;
            $taxAmount = round($amount - $ht, 2);

            return FinanceCreditNote::create([
                'credit_note_number' => $this->nextNumber(),
                'booking_id' => $booking->id,
                'finance_invoice_id' => $this->originalInvoiceId($booking),
                'client_id' => $booking->client_id,
                'organization_account_id' => $booking->customer_organization_id,
                'amount' => $amount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'currency' => strtoupper((string) ($booking->currency ?? 'EUR')),
                'reason' => $reason,
                'stripe_refund_id' => $stripeRefundId,
                'issued_at' => now(),
            ]);
        });
    }

    /** Numéro séquentiel par année, sans trou : AV-{année}-{00001}. */
    protected function nextNumber(): string
    {
        $year = now()->format('Y');

        $seq = FinanceCreditNote::query()
            ->whereYear('issued_at', $year)
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('AV-%s-%05d', $year, $seq);
    }

    protected function originalInvoiceId(Booking $booking): ?int
    {
        if (! Schema::hasColumn('finance_invoices', 'booking_id')) {
            return null;
        }

        return FinanceInvoice::query()
            ->where('booking_id', $booking->id)
            ->latest('id')
            ->value('id');
    }
}
