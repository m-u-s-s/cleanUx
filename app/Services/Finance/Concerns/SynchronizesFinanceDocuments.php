<?php

namespace App\Services\Finance\Concerns;

use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\FinanceQuote;
use App\Models\FinanceReminder;
use App\Models\Booking;
use App\Notifications\FinanceReminderNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait SynchronizesFinanceDocuments
{
    public function syncQuoteForRendezVous(Booking $rdv): FinanceQuote
    {
        $amounts = $this->amountBreakdownFor($rdv);

        return DB::transaction(function () use ($rdv, $amounts) {
            $quote = FinanceQuote::query()->updateOrCreate(
                ['rendez_vous_id' => $rdv->id],
                [
                    'client_id' => $rdv->client_id,
                    'organization_account_id' => $rdv->organization_account_id,
                    'quote_number' => FinanceQuote::query()->where('rendez_vous_id', $rdv->id)->value('quote_number') ?: $this->nextQuoteNumber($rdv),
                    'status' => $this->quoteStatusFor($rdv),
                    'currency' => 'EUR',
                    'subtotal' => $amounts['subtotal'],
                    'tax_rate' => $amounts['tax_rate'],
                    'tax_amount' => $amounts['tax_amount'],
                    'total_amount' => $amounts['total_amount'],
                    'issued_at' => now(),
                    'valid_until' => now()->addDays((int) ($amounts['quote_validity_days'] ?? 15)),
                    'accepted_at' => in_array($rdv->status, ['confirme', 'en_route', 'sur_place', 'termine'], true)
                        ? (FinanceQuote::query()->where('rendez_vous_id', $rdv->id)->value('accepted_at') ?: now())
                        : null,
                    'snapshot' => array_merge($this->snapshotFor($rdv), [
                        'finance_breakdown' => $amounts,
                    ]),
                    'meta' => [
                        'booking_reference' => $rdv->booking_reference,
                        'market' => $rdv->organization_account_id ? 'entreprise' : 'particulier',
                        'quote_validity_days' => (int) ($amounts['quote_validity_days'] ?? 15),
                    ],
                ]
            );

            return $quote->fresh();
        });
    }

    public function syncInvoiceForRendezVous(Booking $rdv): ?FinanceInvoice
    {
        if (! in_array($rdv->status, ['confirme', 'en_route', 'sur_place', 'termine'], true)) {
            return null;
        }

        $quote = $this->syncQuoteForRendezVous($rdv);
        $amounts = $this->amountBreakdownFor($rdv);

        return DB::transaction(function () use ($rdv, $quote, $amounts) {
            $existingBalance = FinanceInvoice::query()->where('rendez_vous_id', $rdv->id)->value('balance_due');
            $existingIssuedAt = FinanceInvoice::query()->where('rendez_vous_id', $rdv->id)->value('issued_at');
            $existingDueAt = FinanceInvoice::query()->where('rendez_vous_id', $rdv->id)->value('due_at');

            $invoice = FinanceInvoice::query()->updateOrCreate(
                ['rendez_vous_id' => $rdv->id],
                [
                    'finance_quote_id' => $quote->id,
                    'client_id' => $rdv->client_id,
                    'organization_account_id' => $rdv->organization_account_id,
                    'invoice_number' => FinanceInvoice::query()->where('rendez_vous_id', $rdv->id)->value('invoice_number') ?: $this->nextInvoiceNumber($rdv),
                    'status' => $this->invoiceStatusFor($rdv),
                    'currency' => 'EUR',
                    'subtotal' => $amounts['subtotal'],
                    'tax_rate' => $amounts['tax_rate'],
                    'tax_amount' => $amounts['tax_amount'],
                    'total_amount' => $amounts['total_amount'],
                    'balance_due' => $existingBalance ?? $amounts['total_amount'],
                    'issued_at' => $existingIssuedAt ?: now(),
                    'due_at' => $existingDueAt ?: now()->addDays((int) ($amounts['payment_terms_days'] ?? ($rdv->organization_account_id ? 30 : 14))),
                    'paid_at' => null,
                    'snapshot' => array_merge($this->snapshotFor($rdv), [
                        'finance_breakdown' => $amounts,
                    ]),
                    'meta' => [
                        'booking_reference' => $rdv->booking_reference,
                        'source_status' => $rdv->status,
                        'payment_terms_days' => (int) ($amounts['payment_terms_days'] ?? ($rdv->organization_account_id ? 30 : 14)),
                    ],
                ]
            );

            $invoice->refreshPaymentStatus();

            return $invoice->fresh();
        });
    }

    public function syncDocumentsForRows(iterable $rendezVousRows): array
    {
        $quotes = 0;
        $invoices = 0;

        foreach ($rendezVousRows as $rdv) {
            if (! $rdv instanceof RendezVous) {
                continue;
            }

            $this->syncQuoteForRendezVous($rdv);
            $quotes++;

            if ($this->syncInvoiceForRendezVous($rdv)) {
                $invoices++;
            }
        }

        return compact('quotes', 'invoices');
    }

    public function issueInvoice(FinanceInvoice $invoice, ?Carbon $dueAt = null): FinanceInvoice
    {
        $invoice->forceFill([
            'status' => 'issued',
            'issued_at' => $invoice->issued_at ?: now(),
            'due_at' => $dueAt ?: $invoice->due_at ?: now()->addDays(14),
        ])->save();

        return $invoice->fresh();
    }

    public function recordPayment(FinanceInvoice $invoice, float $amount, array $attributes = []): FinancePayment
    {
        $payment = $invoice->payments()->create([
            'payment_reference' => Arr::get($attributes, 'payment_reference') ?: 'PAY-' . now()->format('YmdHis') . '-' . $invoice->id,
            'provider' => Arr::get($attributes, 'provider'),
            'method' => Arr::get($attributes, 'method', 'manual'),
            'status' => Arr::get($attributes, 'status', 'paid'),
            'amount' => round($amount, 2),
            'paid_at' => Arr::get($attributes, 'paid_at', now()),
            'external_reference' => Arr::get($attributes, 'external_reference'),
            'notes' => Arr::get($attributes, 'notes'),
            'meta' => Arr::get($attributes, 'meta'),
        ]);

        $invoice->refreshPaymentStatus();

        return $payment;
    }

    public function sendReminder(FinanceInvoice $invoice, string $type = 'gentle'): FinanceReminder
    {
        $invoice->loadMissing(['client', 'organizationAccount']);

        $recipientEmail = $invoice->client?->email
            ?: $invoice->organizationAccount?->billing_email
            ?: $invoice->organizationAccount?->email;

        $reminder = $invoice->reminders()->create([
            'reminder_type' => $type,
            'channel' => 'mail',
            'status' => 'pending',
            'recipient_email' => $recipientEmail,
            'meta' => [
                'invoice_number' => $invoice->invoice_number,
                'balance_due' => (float) $invoice->balance_due,
            ],
        ]);

        try {
            if ($invoice->client && $invoice->client->email) {
                $invoice->client->notify(new FinanceReminderNotification($invoice, $type));
            }

            $reminder->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $reminder->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ])->save();
        }

        return $reminder->fresh();
    }

    public function syncAllEligible(): array
    {
        $quotes = 0;
        $invoices = 0;

        Booking::query()
            ->with(['client', 'organizationAccount', 'organizationSite', 'serviceCatalog', 'serviceZone'])
            ->chunkById(100, function ($rows) use (&$quotes, &$invoices) {
                foreach ($rows as $rdv) {
                    $this->syncQuoteForRendezVous($rdv);
                    $quotes++;

                    if ($this->syncInvoiceForRendezVous($rdv)) {
                        $invoices++;
                    }
                }
            });

        return compact('quotes', 'invoices');
    }

    public function sendDueReminders(): int
    {
        $count = 0;

        FinanceInvoice::query()
            ->whereIn('status', ['issued', 'sent', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->whereNotNull('due_at')
            ->with('client')
            ->get()
            ->each(function (FinanceInvoice $invoice) use (&$count) {
                $type = now()->gt($invoice->due_at) ? 'overdue' : 'gentle';

                $alreadySentRecently = $invoice->reminders()
                    ->where('reminder_type', $type)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();

                if (! $alreadySentRecently) {
                    $this->sendReminder($invoice, $type);
                    $count++;
                }
            });

        return $count;
    }
}
