<?php

namespace App\Support\Finance;

use App\Models\FinanceInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** Single source of truth for rendering a client invoice PDF (relations + view + filename). */
class InvoicePdf
{
    public static function filename(FinanceInvoice $invoice): string
    {
        return 'facture-'.($invoice->invoice_number ?: $invoice->id).'.pdf';
    }

    /** Loads the relations the PDF view needs and returns a DomPDF download response. */
    public static function download(FinanceInvoice $invoice): Response
    {
        $invoice->loadMissing(['client', 'organizationAccount', 'rendezVous.serviceZone', 'rendezVous.organizationSite', 'payments']);

        return Pdf::loadView('client.finance.invoice-pdf', ['invoice' => $invoice])->download(self::filename($invoice));
    }
}
