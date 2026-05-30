<?php

namespace App\Support\Finance;

use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the client invoice summary block, payment-health
 * indicator, and latest-payment-events feed.
 *
 * All queries are scoped through ClientFinanceDocumentScope so isolation rules
 * (own + org/site) are identical to the web Livewire component.
 *
 * INTENTIONAL DIVERGENCE — currency_symbol resolution:
 *   The web Livewire component (FinanceDocumentsClient::getFinanceSummaryProperty)
 *   resolves the currency symbol from the most-recent QUOTE first, falling back
 *   to the most-recent invoice. Quotes are not exposed on the mobile API (they
 *   are a B2B web-only feature), so this class resolves the symbol from the
 *   most-recent INVOICE only. The divergence is by design and documented here
 *   so tests must NOT assert equality of `currency_symbol` between the two sides.
 *   See tests/Feature/Finance/ClientInvoiceSummaryParityTest.php.
 */
class ClientInvoiceSummary
{
    /**
     * @return array{
     *     summary: array{
     *         invoices_count: int,
     *         paid_count: int,
     *         partial_count: int,
     *         overdue_count: int,
     *         outstanding_total: float,
     *         next_due_at: string|null,
     *         currency_symbol: string
     *     },
     *     payment_health: array{tone: string, label: string, title: string, message: string},
     *     latest_payment_events: list<array{id: int, amount: float, status: string, reference: string, date: string|null}>
     * }
     */
    public static function for(User $user): array
    {
        $summary = self::buildSummary($user);

        return [
            'summary' => $summary,
            'payment_health' => self::buildPaymentHealth($summary),
            'latest_payment_events' => self::buildLatestPaymentEvents($user),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal helpers (non-private so PHPStan sees them without static:: warning)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array{invoices_count: int, paid_count: int, partial_count: int, overdue_count: int, outstanding_total: float, next_due_at: string|null, currency_symbol: string}
     */
    protected static function buildSummary(User $user): array
    {
        $baseQuery = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $user);

        /** @var \Illuminate\Database\Eloquent\Collection<int, FinanceInvoice> $invoiceRows */
        $invoiceRows = (clone $baseQuery)->get(['id', 'balance_due', 'status', 'due_at', 'currency', 'snapshot', 'meta']);

        /** @var FinanceInvoice|null $firstInvoice */
        $firstInvoice = (clone $baseQuery)->latest('issued_at')->first();
        $currencySymbol = $firstInvoice?->documentCurrencySymbol() ?? '€';

        /** @var FinanceInvoice|null $nextDueInvoice */
        $nextDueInvoice = $invoiceRows
            ->filter(fn (FinanceInvoice $invoice) => (float) $invoice->balance_due > 0 && filled($invoice->due_at))
            ->sortBy('due_at')
            ->first();

        $nextDueAt = null;
        if ($nextDueInvoice !== null) {
            $dueAt = $nextDueInvoice->due_at;
            if ($dueAt instanceof Carbon) {
                $nextDueAt = $dueAt->toIso8601String();
            } elseif ($dueAt !== null) {
                $nextDueAt = (string) $dueAt;
            }
        }

        return [
            'invoices_count' => $invoiceRows->count(),
            'paid_count' => $invoiceRows->where('status', 'paid')->count(),
            'partial_count' => $invoiceRows->where('status', 'partial')->count(),
            'overdue_count' => $invoiceRows->where('status', 'overdue')->count(),
            'outstanding_total' => round((float) $invoiceRows->sum('balance_due'), 2),
            'next_due_at' => $nextDueAt,
            'currency_symbol' => $currencySymbol,
        ];
    }

    /**
     * Mirrors FinanceDocumentsClient::getPaymentHealthProperty().
     *
     * @param  array{invoices_count: int, paid_count: int, partial_count: int, overdue_count: int, outstanding_total: float, next_due_at: string|null, currency_symbol: string}  $summary
     * @return array{tone: string, label: string, title: string, message: string}
     */
    protected static function buildPaymentHealth(array $summary): array
    {
        if ($summary['overdue_count'] > 0) {
            return [
                'tone' => 'rose',
                'label' => 'Action requise',
                'title' => 'Facture(s) en retard',
                'message' => 'Une ou plusieurs factures nécessitent votre attention.',
            ];
        }

        if ($summary['outstanding_total'] > 0) {
            return [
                'tone' => 'amber',
                'label' => 'À surveiller',
                'title' => 'Solde ouvert',
                'message' => 'Vous avez encore un montant à régler.',
            ];
        }

        return [
            'tone' => 'emerald',
            'label' => 'À jour',
            'title' => 'Situation saine',
            'message' => 'Aucun retard de paiement détecté.',
        ];
    }

    /**
     * Mirrors FinanceDocumentsClient::getLatestPaymentEventsProperty() — returns
     * the 5 most-recent payment events across the latest 15 invoices.
     *
     * @return list<array{id: int, amount: float, status: string, reference: string, date: string|null}>
     */
    protected static function buildLatestPaymentEvents(User $user): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, FinanceInvoice> $invoices */
        $invoices = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $user)
            ->with('payments')
            ->latest('issued_at')
            ->limit(15)
            ->get();

        /** @var Collection<int, FinancePayment> $payments */
        $payments = $invoices->flatMap(fn (FinanceInvoice $invoice) => $invoice->payments);

        return $payments
            ->sortByDesc(fn ($payment) => $payment->paid_at ?? $payment->created_at)
            ->take(5)
            ->values()
            ->map(fn ($payment) => [
                'id' => (int) $payment->id,
                'amount' => (float) $payment->amount,
                'status' => (string) $payment->status,
                'reference' => (string) ($payment->payment_reference ?? ''),
                'date' => optional($payment->paid_at ?? $payment->created_at)->toIso8601String(),
            ])
            ->all();
    }
}
