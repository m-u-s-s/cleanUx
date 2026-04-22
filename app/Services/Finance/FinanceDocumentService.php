<?php

namespace App\Services\Finance;

use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\FinanceQuote;
use App\Models\FinanceReminder;
use App\Models\RendezVous;
use App\Notifications\FinanceReminderNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceDocumentService
{
    public function syncQuoteForRendezVous(RendezVous $rdv): FinanceQuote
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

    public function syncInvoiceForRendezVous(RendezVous $rdv): ?FinanceInvoice
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

        RendezVous::query()
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

    public function amountBreakdownFor(RendezVous $rdv): array
    {
        $pricing = (array) ($rdv->pricing_snapshot ?? []);
        $basePrice = round((float) (
            data_get($pricing, 'devis_estime')
            ?? $rdv->devis_estime
            ?? data_get($pricing, 'base_price_override')
            ?? data_get($pricing, 'base_price')
            ?? $rdv->serviceCatalog?->base_price
            ?? 0
        ), 2);

        $travelSurcharge = round((float) (
            data_get($pricing, 'travel_surcharge')
            ?? data_get($pricing, 'zone.travel_surcharge')
            ?? data_get($pricing, 'zone_snapshot.travel_surcharge')
            ?? $rdv->serviceZone?->travel_surcharge
            ?? 0
        ), 2);

        $discountRate = round((float) (
            data_get($pricing, 'corporate_context.negotiated_discount_rate')
            ?? data_get($rdv->organizationAccount?->metadata, 'finance.negotiated_discount_rate')
            ?? data_get($rdv->organizationAccount?->metadata, 'contract.negotiated_discount_rate')
            ?? 0
        ), 2);

        $discountAmount = round((($basePrice + $travelSurcharge) * max($discountRate, 0)) / 100, 2);
        $subtotal = round(max(($basePrice + $travelSurcharge) - $discountAmount, 0), 2);

        $taxRate = round((float) (
            data_get($pricing, 'tax_rate')
            ?? data_get($rdv->organizationAccount?->metadata, 'finance.tax_rate')
            ?? 21.00
        ), 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $durationMinutes = (int) (
            $rdv->duree_reelle
            ?? $rdv->duree
            ?? data_get($pricing, 'duration_minutes')
            ?? $rdv->serviceCatalog?->default_duration_minutes
            ?? 0
        );

        $hourlyCost = round((float) (
            data_get($rdv->employe?->metadata, 'finance.hourly_cost')
            ?? data_get($rdv->organizationAccount?->metadata, 'finance.default_employee_hourly_cost')
            ?? 18
        ), 2);

        $fixedMissionCost = round((float) (
            data_get($rdv->organizationAccount?->metadata, 'finance.fixed_mission_cost')
            ?? data_get($pricing, 'fixed_mission_cost')
            ?? 0
        ), 2);

        $estimatedInternalCost = round((($durationMinutes / 60) * $hourlyCost) + $fixedMissionCost, 2);
        $estimatedMarginAmount = round($subtotal - $estimatedInternalCost, 2);
        $estimatedMarginRate = $subtotal > 0 ? round(($estimatedMarginAmount / $subtotal) * 100, 1) : 0.0;

        $paymentTermsDays = (int) (
            data_get($pricing, 'corporate_context.payment_terms_days')
            ?? data_get($rdv->organizationAccount?->metadata, 'finance.payment_terms_days')
            ?? data_get($rdv->organizationAccount?->metadata, 'contract.payment_terms_days')
            ?? ($rdv->organization_account_id ? 30 : 14)
        );

        $quoteValidityDays = (int) (
            data_get($rdv->organizationAccount?->metadata, 'finance.quote_validity_days')
            ?? 15
        );

        return [
            'base_price' => $basePrice,
            'travel_surcharge' => $travelSurcharge,
            'discount_rate' => $discountRate,
            'discount_amount' => $discountAmount,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'duration_minutes' => $durationMinutes,
            'employee_hourly_cost' => $hourlyCost,
            'fixed_mission_cost' => $fixedMissionCost,
            'estimated_internal_cost' => $estimatedInternalCost,
            'estimated_margin_amount' => $estimatedMarginAmount,
            'estimated_margin_rate' => $estimatedMarginRate,
            'payment_terms_days' => $paymentTermsDays,
            'quote_validity_days' => $quoteValidityDays,
        ];
    }

    public function invoiceHealthSummary(?Collection $invoiceRows = null): array
    {
        $rows = $invoiceRows ?: FinanceInvoice::query()->with(['payments', 'reminders'])->get();

        $outstanding = round((float) $rows->sum('balance_due'), 2);
        $paid = round((float) $rows->filter(fn(FinanceInvoice $invoice) => (float) $invoice->balance_due <= 0)->sum('total_amount'), 2);
        $overdue = $rows->filter(function (FinanceInvoice $invoice) {
            return (float) $invoice->balance_due > 0
                && $invoice->due_at !== null
                && now()->gt($invoice->due_at);
        });

        return [
            'invoice_count' => $rows->count(),
            'outstanding_balance' => $outstanding,
            'paid_total' => $paid,
            'overdue_count' => $overdue->count(),
            'overdue_balance' => round((float) $overdue->sum('balance_due'), 2),
            'partial_count' => $rows->where('status', 'partial')->count(),
        ];
    }

    protected function quoteStatusFor(RendezVous $rdv): string
    {
        return match ($rdv->status) {
            'annule', 'refuse' => 'cancelled',
            'confirme', 'en_route', 'sur_place', 'termine' => 'accepted',
            default => 'draft',
        };
    }

    protected function invoiceStatusFor(RendezVous $rdv): string
    {
        return match ($rdv->status) {
            'termine' => 'issued',
            'en_route', 'sur_place' => 'sent',
            default => 'draft',
        };
    }

    protected function snapshotFor(RendezVous $rdv): array
    {
        $serviceName = $rdv->service_display_name
            ?: $rdv->serviceCatalog?->name
            ?: data_get($rdv->pricing_snapshot, 'service_name')
            ?: data_get($rdv->pricing_snapshot, 'service.name')
            ?: data_get($rdv->zone_snapshot, 'service_name')
            ?: $rdv->motif;

        $serviceIdentifier = $rdv->service_identifier_display
            ?: data_get($rdv->pricing_snapshot, 'service_identifier')
            ?: data_get($rdv->pricing_snapshot, 'service.service_identifier')
            ?: data_get($rdv->zone_snapshot, 'service_identifier')
            ?: $rdv->serviceCatalog?->code
            ?: $rdv->serviceCatalog?->slug;

        $postalCode = $rdv->postalCode?->code
            ?: data_get($rdv->pricing_snapshot, 'postal_code')
            ?: data_get($rdv->zone_snapshot, 'postal_code')
            ?: $rdv->code_postal;

        $city = $rdv->postalCode?->city_name
            ?: $rdv->postalCode?->commune?->name
            ?: data_get($rdv->pricing_snapshot, 'ville')
            ?: data_get($rdv->zone_snapshot, 'ville')
            ?: $rdv->ville;

        $locationDisplay = collect([$rdv->adresse, $postalCode, $city])
            ->filter(fn($value) => filled($value))
            ->implode(', ');

        return [
            'booking_reference' => $rdv->booking_reference,
            'service_name' => $serviceName,
            'service_identifier' => $serviceIdentifier,
            'date' => optional($rdv->date)->toDateString(),
            'heure' => $rdv->heure,
            'adresse' => $rdv->adresse,
            'postal_code' => $postalCode,
            'ville' => $city,
            'location_display' => $locationDisplay,
            'zone_name' => $rdv->serviceZone?->name,
            'organization_name' => $rdv->organizationAccount?->name,
            'site_name' => $rdv->organizationSite?->name,
            'client_name' => $rdv->client?->name,
        ];
    }

    protected function nextQuoteNumber(RendezVous $rdv): string
    {
        return 'DEV-' . now()->format('Y') . '-' . str_pad((string) ($rdv->id ?: 0), 6, '0', STR_PAD_LEFT);
    }

    protected function nextInvoiceNumber(RendezVous $rdv): string
    {
        return 'FAC-' . now()->format('Y') . '-' . str_pad((string) ($rdv->id ?: 0), 6, '0', STR_PAD_LEFT);
    }
}
