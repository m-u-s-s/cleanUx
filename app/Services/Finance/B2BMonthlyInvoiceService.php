<?php

namespace App\Services\Finance;

use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\RendezVous;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class B2BMonthlyInvoiceService
{
    public function generateForOrganization(
        OrganizationAccount $organization,
        Carbon|string $periodStart,
        Carbon|string $periodEnd
    ): ?FinanceInvoice {
        $periodStart = Carbon::parse($periodStart)->startOfDay();
        $periodEnd = Carbon::parse($periodEnd)->endOfDay();

        $rendezVous = RendezVous::query()
            ->with(['client', 'organizationSite', 'serviceCatalog'])
            ->where('organization_account_id', $organization->id)
            ->whereIn('status', [BookingStatus::TERMINE, BookingStatus::CONFIRME])
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereDoesntHave('financeInvoice')
            ->get();

        if ($rendezVous->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($organization, $periodStart, $periodEnd, $rendezVous) {
            $subtotal = round((float) $rendezVous->sum('devis_estime'), 2);
            $taxRate = (float) data_get($organization->metadata, 'tax_rate', 21);
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $total = round($subtotal + $taxAmount, 2);

            $invoice = FinanceInvoice::create([
                'client_id' => $organization->users()->clientFacing()->value('id'),
                'organization_account_id' => $organization->id,
                'invoice_number' => $this->nextInvoiceNumber($organization),
                'invoice_type' => 'b2b_monthly',
                'status' => 'issued',
                'currency' => (string) data_get($organization->metadata, 'currency', 'EUR'),
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'balance_due' => $total,
                'issued_at' => now(),
                'due_at' => now()->addDays((int) data_get($organization->metadata, 'payment_terms_days', 30)),
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodEnd,
                'site_breakdown' => $this->siteBreakdown($rendezVous),
                'snapshot' => [
                    'organization' => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'legal_name' => $organization->legal_name,
                        'tva_number' => $organization->tva_number,
                        'billing_email' => $organization->billing_email,
                    ],
                    'period' => [
                        'start' => $periodStart->toDateString(),
                        'end' => $periodEnd->toDateString(),
                    ],
                    'lines' => $this->lines($rendezVous),
                ],
                'meta' => [
                    'generated_by' => 'b2b_monthly_invoice_service',
                    'rendez_vous_count' => $rendezVous->count(),
                ],
            ]);

            foreach ($rendezVous as $rdv) {
                $rdv->financeInvoice()->create([
                    'client_id' => $rdv->client_id,
                    'organization_account_id' => $organization->id,
                    'invoice_number' => $invoice->invoice_number.'-'.$rdv->id,
                    'invoice_type' => 'b2b_child_line',
                    'status' => 'included_in_batch',
                    'currency' => $invoice->currency,
                    'subtotal' => (float) $rdv->devis_estime,
                    'tax_rate' => $invoice->tax_rate,
                    'tax_amount' => 0,
                    'total_amount' => (float) $rdv->devis_estime,
                    'balance_due' => 0,
                    'issued_at' => now(),
                    'due_at' => $invoice->due_at,
                    'billing_period_start' => $periodStart,
                    'billing_period_end' => $periodEnd,
                    'snapshot' => [
                        'parent_invoice_id' => $invoice->id,
                        'parent_invoice_number' => $invoice->invoice_number,
                    ],
                    'meta' => [
                        'included_in_b2b_monthly_invoice_id' => $invoice->id,
                    ],
                ]);
            }

            ActivityLogger::log('b2b_monthly_invoice_generated', $invoice, [
                'organization_account_id' => $organization->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'total_amount' => $total,
                'rendez_vous_count' => $rendezVous->count(),
            ]);

            return $invoice;
        });
    }

    protected function nextInvoiceNumber(OrganizationAccount $organization): string
    {
        return 'B2B-'.$organization->id.'-'.now()->format('Ym').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    protected function lines(Collection $rendezVous): array
    {
        return $rendezVous->map(fn (RendezVous $rdv) => [
            'rendez_vous_id' => $rdv->id,
            'booking_reference' => $rdv->booking_reference,
            'date' => $rdv->date?->format('Y-m-d'),
            'heure' => substr((string) $rdv->heure, 0, 5),
            'site' => $rdv->organizationSite?->name,
            'cost_center' => $rdv->cost_center ?? data_get($rdv->pricing_snapshot, 'cost_center'),
            'service' => $rdv->service_display_name,
            'amount' => (float) $rdv->devis_estime,
        ])->values()->all();
    }

    protected function siteBreakdown(Collection $rendezVous): array
    {
        return $rendezVous
            ->groupBy(fn (RendezVous $rdv) => $rdv->organizationSite?->name ?? 'Sans site')
            ->map(fn (Collection $items, string $siteName) => [
                'site' => $siteName,
                'count' => $items->count(),
                'subtotal' => round((float) $items->sum('devis_estime'), 2),
            ])
            ->values()
            ->all();
    }
}