<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Services\Finance\FinanceDocumentService;
use Illuminate\Database\Eloquent\Builder;

trait BuildsFinanceCenterQueries
{
    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingDateFrom(): void { $this->resetPage(); }
    public function updatingDateTo(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingZoneId(): void { $this->resetPage(); }
    public function updatingServiceId(): void { $this->resetPage(); }
    public function updatingOrganizationId(): void { $this->resetPage(); }
    public function updatingMarket(): void { $this->resetPage(); }
    public function updatingViewMode(): void { $this->resetPage(); }
    public function updatingPaymentFilter(): void { $this->resetPage(); }

    public function getZonesProperty()
    {
        return ServiceZone::query()->orderBy('name')->get();
    }

    public function getServicesProperty()
    {
        return ServiceCatalog::query()->orderBy('name')->get();
    }

    public function getOrganizationsProperty()
    {
        return OrganizationAccount::query()->orderBy('name')->get();
    }

    protected function financeService(): FinanceDocumentService
    {
        return app(FinanceDocumentService::class);
    }

    protected function baseQuery(): Builder
    {
        return RendezVous::query()
            ->with(['client', 'employe', 'organizationAccount', 'organizationSite', 'serviceCatalog', 'serviceZone', 'financeQuote', 'financeInvoice.payments', 'financeInvoice.reminders'])
            ->when(filled($this->dateFrom), fn (Builder $q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when(filled($this->dateTo), fn (Builder $q) => $q->whereDate('date', '<=', $this->dateTo))
            ->when(filled($this->status), fn (Builder $q) => $q->where('status', $this->status))
            ->when(filled($this->zoneId), fn (Builder $q) => $q->where('service_zone_id', $this->zoneId))
            ->when(filled($this->serviceId), fn (Builder $q) => $q->where('service_catalog_id', $this->serviceId))
            ->when(filled($this->organizationId), fn (Builder $q) => $q->where('organization_account_id', $this->organizationId))
            ->when($this->market === 'entreprise', fn (Builder $q) => $q->whereNotNull('organization_account_id'))
            ->when($this->market === 'particulier', fn (Builder $q) => $q->whereNull('organization_account_id'))
            ->when($this->viewMode === 'quotes', fn (Builder $q) => $q->whereIn('status', ['en_attente', 'confirme']))
            ->when($this->viewMode === 'invoices', fn (Builder $q) => $q->whereIn('status', ['confirme', 'en_route', 'sur_place', 'termine']))
            ->when($this->viewMode === 'cancelled', fn (Builder $q) => $q->whereIn('status', ['annule', 'refuse']))
            ->when($this->paymentFilter === 'quoted_only', fn (Builder $q) => $q->whereHas('financeQuote')->whereDoesntHave('financeInvoice'))
            ->when($this->paymentFilter === 'pending', fn (Builder $q) => $q->whereHas('financeInvoice', fn (Builder $sq) => $sq->where('balance_due', '>', 0)))
            ->when($this->paymentFilter === 'paid', fn (Builder $q) => $q->whereHas('financeInvoice', fn (Builder $sq) => $sq->where('balance_due', '<=', 0)))
            ->when($this->paymentFilter === 'overdue', fn (Builder $q) => $q->whereHas('financeInvoice', function (Builder $sq) {
                $sq->where('balance_due', '>', 0)->whereNotNull('due_at')->where('due_at', '<', now());
            }))
            ->when(filled($this->search), function (Builder $query) {
                $term = '%' . $this->search . '%';

                $query->where(function (Builder $q) use ($term) {
                    $q->where('booking_reference', 'like', $term)
                        ->orWhere('adresse', 'like', $term)
                        ->orWhere('ville', 'like', $term)
                        ->orWhereHas('client', fn (Builder $sq) => $sq->where('name', 'like', $term))
                        ->orWhereHas('organizationAccount', fn (Builder $sq) => $sq->where('name', 'like', $term))
                        ->orWhereHas('serviceCatalog', fn (Builder $sq) => $sq->where('name', 'like', $term));
                });
            });
    }

    public function getRowsProperty()
    {
        return $this->baseQuery()
            ->orderByDesc('date')
            ->orderByDesc('heure')
            ->paginate(12);
    }

    public function getKpisProperty(): array
    {
        $rows = $this->baseQuery()->get();
        $totalHtva = round((float) $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['subtotal']), 2);
        $entrepriseHtva = round((float) $rows->whereNotNull('organization_account_id')->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['subtotal']), 2);
        $toInvoice = round((float) $rows->filter(fn (RendezVous $rdv) => in_array($rdv->status, ['confirme', 'en_route', 'sur_place', 'termine']))->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['subtotal']), 2);
        $avgBasket = $rows->count() > 0 ? round($totalHtva / $rows->count(), 2) : 0;
        $margin = round((float) $rows->sum(fn (RendezVous $rdv) => $this->amountBreakdown($rdv)['estimated_margin_amount']), 2);

        $invoices = FinanceInvoice::query()
            ->whereIn('rendez_vous_id', $rows->pluck('id'))
            ->get();

        $invoiceHealth = $this->financeService()->invoiceHealthSummary($invoices);

        return [
            'total_htva' => $totalHtva,
            'entreprise_htva' => $entrepriseHtva,
            'to_invoice_htva' => $toInvoice,
            'avg_basket_htva' => $avgBasket,
            'margin_estimate' => $margin,
            'count' => $rows->count(),
            'manual_validation_count' => $rows->filter(fn (RendezVous $rdv) => (bool) data_get($rdv->pricing_snapshot, 'requires_manual_validation', false))->count(),
            'completed_count' => $rows->where('status', 'termine')->count(),
            'cancelled_count' => $rows->filter(fn (RendezVous $rdv) => in_array($rdv->status, ['annule', 'refuse'], true))->count(),
            'outstanding_balance' => $invoiceHealth['outstanding_balance'],
            'paid_total' => $invoiceHealth['paid_total'],
            'overdue_count' => $invoiceHealth['overdue_count'],
            'overdue_balance' => $invoiceHealth['overdue_balance'],
        ];
    }

    public function selectRendezVous(int $rendezVousId): void
    {
        $this->selectedRendezVousId = $rendezVousId;

        $selected = $this->getSelectedRendezVousProperty();
        if ($selected?->financeInvoice) {
            $this->manualPaymentAmount = (string) number_format((float) $selected->financeInvoice->balance_due, 2, '.', '');
        }
    }

    public function getSelectedRendezVousProperty(): ?RendezVous
    {
        if (! $this->selectedRendezVousId) {
            return $this->rows->first();
        }

        return RendezVous::query()
            ->with(['client', 'employe', 'organizationAccount', 'organizationSite', 'serviceCatalog', 'serviceZone', 'financeQuote', 'financeInvoice.payments', 'financeInvoice.reminders'])
            ->find($this->selectedRendezVousId);
    }

    protected function amountBreakdown(RendezVous $rdv): array
    {
        return $this->financeService()->amountBreakdownFor($rdv);
    }

    public function amountHtva(RendezVous $rdv): float
    {
        return $this->amountBreakdown($rdv)['subtotal'];
    }

    public function amountTva(RendezVous $rdv): float
    {
        return $this->amountBreakdown($rdv)['tax_amount'];
    }

    public function amountTvac(RendezVous $rdv): float
    {
        return $this->amountBreakdown($rdv)['total_amount'];
    }

    public function marginEstimate(RendezVous $rdv): float
    {
        return $this->amountBreakdown($rdv)['estimated_margin_amount'];
    }

    public function financeStage(RendezVous $rdv): string
    {
        if ($rdv->financeInvoice && (float) $rdv->financeInvoice->balance_due <= 0) {
            return 'Payé';
        }

        if ($rdv->financeInvoice && $rdv->financeInvoice->due_at && now()->gt($rdv->financeInvoice->due_at) && (float) $rdv->financeInvoice->balance_due > 0) {
            return 'En retard';
        }

        return match ($rdv->status) {
            'en_attente' => 'Devis en attente',
            'confirme' => 'À facturer',
            'en_route', 'sur_place' => 'Mission en cours',
            'termine' => 'Facturable',
            'annule', 'refuse' => 'Annulé',
            default => 'À suivre',
        };
    }

    protected function quoteNumber(RendezVous $rdv): string
    {
        return $rdv->financeQuote?->quote_number ?: 'DEV-' . ($rdv->booking_reference ?: $rdv->id);
    }

    protected function invoiceNumber(RendezVous $rdv): string
    {
        return $rdv->financeInvoice?->invoice_number ?: 'FAC-' . ($rdv->booking_reference ?: $rdv->id);
    }
}
