<?php

namespace App\Support\Livewire\Concerns\Admin;

use App\Models\FinanceInvoice;
use App\Models\RendezVous;
use Barryvdh\DomPDF\Facade\Pdf;

trait HandlesFinanceDocuments
{
    public function downloadQuotePdf(int $rendezVousId)
    {
        $rdv = $this->loadRendezVous($rendezVousId);
        $pdf = Pdf::loadView('pdf.devis-rendezvous', [
            'rdv' => $rdv,
            'quoteNumber' => $this->quoteNumber($rdv),
            'amountHtva' => $this->amountHtva($rdv),
            'amountTva' => $this->amountTva($rdv),
            'amountTvac' => $this->amountTvac($rdv),
            'taxRate' => $this->taxRate,
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $this->quoteNumber($rdv) . '.pdf');
    }

    public function downloadInvoicePdf(int $rendezVousId)
    {
        $rdv = $this->loadRendezVous($rendezVousId);
        $pdf = Pdf::loadView('pdf.facture-rendezvous', [
            'rdv' => $rdv,
            'invoiceNumber' => $this->invoiceNumber($rdv),
            'amountHtva' => $this->amountHtva($rdv),
            'amountTva' => $this->amountTva($rdv),
            'amountTvac' => $this->amountTvac($rdv),
            'taxRate' => $this->taxRate,
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $this->invoiceNumber($rdv) . '.pdf');
    }

    public function exportFinanceCsv()
    {
        $rows = $this->baseQuery()->orderBy('date')->get();
        $filename = 'finance_export_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'reference', 'date', 'client', 'organisation', 'site', 'zone', 'service', 'statut', 'etape_finance',
                'htva', 'tva', 'tvac', 'marge_estimee', 'solde_restant', 'reference_devis', 'reference_facture', 'type_marche', 'tva_client', 'ville',
            ], ';');

            foreach ($rows as $rdv) {
                fputcsv($handle, [
                    $rdv->booking_reference,
                    optional($rdv->date)->format('Y-m-d'),
                    $rdv->client?->name,
                    $rdv->organizationAccount?->name,
                    $rdv->organizationSite?->name,
                    $rdv->serviceZone?->name,
                    $rdv->service_display_name,
                    $rdv->status,
                    $this->financeStage($rdv),
                    number_format($this->amountHtva($rdv), 2, '.', ''),
                    number_format($this->amountTva($rdv), 2, '.', ''),
                    number_format($this->amountTvac($rdv), 2, '.', ''),
                    number_format($this->marginEstimate($rdv), 2, '.', ''),
                    number_format((float) ($rdv->financeInvoice?->balance_due ?? 0), 2, '.', ''),
                    $this->quoteNumber($rdv),
                    $this->invoiceNumber($rdv),
                    $rdv->organization_account_id ? 'entreprise' : 'particulier',
                    $rdv->organizationAccount?->tva_number,
                    $rdv->ville,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function loadRendezVous(int $rendezVousId): RendezVous
    {
        return RendezVous::query()
            ->with(['client', 'employe', 'organizationAccount', 'organizationSite', 'serviceCatalog', 'serviceZone', 'financeQuote', 'financeInvoice.payments', 'financeInvoice.reminders'])
            ->findOrFail($rendezVousId);
    }

    public function ensureQuoteDocument(int $rendezVousId): void
    {
        $rdv = $this->loadRendezVous($rendezVousId);
        $this->financeService()->syncQuoteForRendezVous($rdv);
        $this->selectedRendezVousId = $rendezVousId;
        session()->flash('success', 'Devis synchronisé.');
    }

    public function ensureInvoiceDocument(int $rendezVousId): void
    {
        $rdv = $this->loadRendezVous($rendezVousId);
        $invoice = $this->financeService()->syncInvoiceForRendezVous($rdv);

        if (! $invoice) {
            session()->flash('warning', 'Aucune facture à générer pour ce rendez-vous dans son état actuel.');
            return;
        }

        $this->selectedRendezVousId = $rendezVousId;
        $this->manualPaymentAmount = (string) number_format((float) $invoice->balance_due, 2, '.', '');
        session()->flash('success', 'Facture synchronisée.');
    }

    public function issueInvoiceNow(int $rendezVousId): void
    {
        $rdv = $this->loadRendezVous($rendezVousId);
        $invoice = $this->financeService()->syncInvoiceForRendezVous($rdv);

        if (! $invoice) {
            session()->flash('warning', 'Impossible d’émettre une facture pour ce rendez-vous.');
            return;
        }

        $this->financeService()->issueInvoice($invoice);
        $this->selectedRendezVousId = $rendezVousId;
        session()->flash('success', 'Facture émise.');
    }

    public function markInvoicePaidNow(int $rendezVousId): void
    {
        $rdv = $this->loadRendezVous($rendezVousId);
        $invoice = $this->financeService()->syncInvoiceForRendezVous($rdv);

        if (! $invoice) {
            session()->flash('warning', 'Aucune facture trouvée à solder.');
            return;
        }

        if ((float) $invoice->balance_due <= 0) {
            session()->flash('success', 'La facture est déjà soldée.');
            return;
        }

        $this->financeService()->recordPayment($invoice, (float) $invoice->balance_due, [
            'method' => 'manual',
            'provider' => 'backoffice',
            'notes' => 'Paiement saisi manuellement depuis le centre finance.',
        ]);

        $this->selectedRendezVousId = $rendezVousId;
        session()->flash('success', 'Paiement enregistré et facture soldée.');
    }

    public function recordPartialPaymentNow(int $rendezVousId): void
    {
        $rdv = $this->loadRendezVous($rendezVousId);
        $invoice = $this->financeService()->syncInvoiceForRendezVous($rdv);

        if (! $invoice) {
            session()->flash('warning', 'Aucune facture trouvée.');
            return;
        }

        $amount = round((float) $this->manualPaymentAmount, 2);

        if ($amount <= 0 || $amount > (float) $invoice->balance_due) {
            session()->flash('warning', 'Montant de paiement invalide.');
            return;
        }

        $this->financeService()->recordPayment($invoice, $amount, [
            'method' => $this->manualPaymentMethod ?: 'manual',
            'provider' => 'backoffice',
            'notes' => 'Paiement partiel saisi manuellement depuis le centre finance.',
        ]);

        $this->selectedRendezVousId = $rendezVousId;
        $this->manualPaymentAmount = (string) number_format((float) $invoice->fresh()->balance_due, 2, '.', '');
        session()->flash('success', 'Paiement partiel enregistré.');
    }

    public function sendInvoiceReminderNow(int $rendezVousId, string $type = 'gentle'): void
    {
        $rdv = $this->loadRendezVous($rendezVousId);
        $invoice = $this->financeService()->syncInvoiceForRendezVous($rdv);

        if (! $invoice instanceof FinanceInvoice) {
            session()->flash('warning', 'Aucune facture trouvée pour envoyer une relance.');
            return;
        }

        $this->financeService()->sendReminder($invoice, $type);
        $this->selectedRendezVousId = $rendezVousId;
        session()->flash('success', 'Relance financière envoyée.');
    }

    public function syncFilteredDocuments(): void
    {
        $stats = $this->financeService()->syncDocumentsForRows($this->baseQuery()->get());
        session()->flash('success', "Documents synchronisés : {$stats['quotes']} devis / {$stats['invoices']} factures.");
    }

    public function syncAllDocuments(): void
    {
        $stats = $this->financeService()->syncAllEligible();
        session()->flash('success', "Synchronisation globale : {$stats['quotes']} devis / {$stats['invoices']} factures.");
    }
}
