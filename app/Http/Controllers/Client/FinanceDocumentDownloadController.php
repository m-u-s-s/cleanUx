<?php

namespace App\Http\Controllers\Client;

use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use App\Services\Entreprise\EntrepriseRoutingService;
use Barryvdh\DomPDF\Facade\Pdf;

class FinanceDocumentDownloadController
{
    public function quote(FinanceQuote $quote, EntrepriseRoutingService $entrepriseRouting)
    {
        $quote->loadMissing(['client', 'organizationAccount', 'rendezVous.serviceZone', 'rendezVous.organizationSite']);
        $this->authorizeDocument($quote->client_id, $quote->organization_account_id, $quote->rendezVous?->organizationSite, $entrepriseRouting);

        return $this->renderPdfOrHtml(
            'client.finance.quote-pdf',
            ['quote' => $quote],
            'devis-' . ($quote->quote_number ?: $quote->id) . '.pdf'
        );
    }

    public function invoice(FinanceInvoice $invoice, EntrepriseRoutingService $entrepriseRouting)
    {
        $invoice->loadMissing(['client', 'organizationAccount', 'rendezVous.serviceZone', 'rendezVous.organizationSite', 'payments']);
        $this->authorizeDocument($invoice->client_id, $invoice->organization_account_id, $invoice->rendezVous?->organizationSite, $entrepriseRouting);

        return $this->renderPdfOrHtml(
            'client.finance.invoice-pdf',
            ['invoice' => $invoice],
            'facture-' . ($invoice->invoice_number ?: $invoice->id) . '.pdf'
        );
    }

    protected function authorizeDocument(?int $clientId, ?int $organizationAccountId, $site, EntrepriseRoutingService $entrepriseRouting): void
    {
        $user = auth()->user();

        abort_unless($user, 403);

        $allowed = (int) $clientId === (int) $user->id;

        if (! $allowed && $organizationAccountId && (int) $organizationAccountId === (int) $user->organization_account_id) {
            $allowed = $site ? $entrepriseRouting->userCanAccessSite($user, $site) : true;
        }

        abort_unless($allowed, 403);
    }

    protected function renderPdfOrHtml(string $view, array $data, string $filename)
    {
        try {
            return Pdf::loadView($view, $data)->download($filename);
        } catch (\Throwable $e) {
            return response()->view($view, $data, 200);
        }
    }
}
