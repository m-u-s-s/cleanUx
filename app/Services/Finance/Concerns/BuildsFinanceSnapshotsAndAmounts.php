<?php

namespace App\Services\Finance\Concerns;

use App\Models\FinanceInvoice;
use App\Models\RendezVous;
use Illuminate\Support\Collection;

trait BuildsFinanceSnapshotsAndAmounts
{
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
        $paid = round((float) $rows->filter(fn (FinanceInvoice $invoice) => (float) $invoice->balance_due <= 0)->sum('total_amount'), 2);
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
        return [
            'booking_reference' => $rdv->booking_reference,
            'service_name' => data_get($rdv->pricing_snapshot, 'service_name', $rdv->serviceCatalog?->name),
            'service_type' => $rdv->service_type,
            'date' => optional($rdv->date)->toDateString(),
            'heure' => $rdv->heure,
            'adresse' => $rdv->adresse,
            'ville' => $rdv->ville,
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
