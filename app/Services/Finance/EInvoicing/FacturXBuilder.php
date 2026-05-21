<?php

namespace App\Services\Finance\EInvoicing;

use App\Models\FinanceInvoice;
use Illuminate\Support\Carbon;

/**
 * Builder PDF/A-3 + XML CII embedded (norme Factur-X / ZUGFeRD).
 *
 * OBLIGATOIRE en France à partir du 01/09/2026 pour B2B.
 * Compatible avec Peppol BIS Billing 3.0 (réseau européen).
 *
 * Workflow :
 *   1. Build XML CII (Cross Industry Invoice) UN/CEFACT depuis FinanceInvoice
 *   2. Build PDF/A-3 standard avec library DomPDF (déjà installée)
 *   3. Embed XML dans PDF/A-3 via pdf-attach (lib externe : moustahanafa/pdf-attach OU symfony/pdf-toolkit)
 *
 * Pour la prod : route via PDP (Plateforme de Dématérialisation Partenaire) certifiée DGFiP
 * ou PPF (Plateforme Publique de Facturation, ChorusPro extension).
 *
 * En MVP : génère PDF + XML séparés. Peppol routing à brancher via partenaire.
 */
class FacturXBuilder
{
    /**
     * Génère le XML CII Cross Industry Invoice depuis une FinanceInvoice.
     * Conforme UN/CEFACT D16B (Factur-X 1.0.06 minimum / Peppol BIS Billing 3.0).
     */
    public function buildXml(FinanceInvoice $invoice): string
    {
        $issuer = $invoice->issuer;
        $client = $invoice->customer;
        $issued = Carbon::parse($invoice->issued_at ?? $invoice->created_at);
        $due = Carbon::parse($invoice->due_at ?? $issued->copy()->addDays(30));

        $totalExcl = (float) ($invoice->amount_excl_tax ?? $invoice->amount ?? 0);
        $totalIncl = (float) ($invoice->amount_incl_tax ?? $invoice->amount ?? 0);
        $tax = $totalIncl - $totalExcl;
        $currency = $invoice->currency ?? 'EUR';

        // Profile MINIMUM pour Factur-X (le plus simple, suffisant pour la plupart B2B FR)
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100" xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
  <rsm:ExchangedDocumentContext>
    <ram:GuidelineSpecifiedDocumentContextParameter>
      <ram:ID>urn:factur-x.eu:1p0:minimum</ram:ID>
    </ram:GuidelineSpecifiedDocumentContextParameter>
  </rsm:ExchangedDocumentContext>
  <rsm:ExchangedDocument>
    <ram:ID>{$this->esc($invoice->reference ?? 'INV-' . $invoice->id)}</ram:ID>
    <ram:TypeCode>380</ram:TypeCode>
    <ram:IssueDateTime>
      <udt:DateTimeString format="102">{$issued->format('Ymd')}</udt:DateTimeString>
    </ram:IssueDateTime>
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:ApplicableHeaderTradeAgreement>
      <ram:SellerTradeParty>
        <ram:Name>{$this->esc(config('app.name', 'CleanUx'))}</ram:Name>
        <ram:SpecifiedLegalOrganization>
          <ram:ID schemeID="0002">{$this->esc(config('accounting_v2.fec_siren', '000000000'))}</ram:ID>
        </ram:SpecifiedLegalOrganization>
        <ram:PostalTradeAddress>
          <ram:CountryID>FR</ram:CountryID>
        </ram:PostalTradeAddress>
      </ram:SellerTradeParty>
      <ram:BuyerTradeParty>
        <ram:Name>{$this->esc($client?->name ?? 'Client')}</ram:Name>
        <ram:PostalTradeAddress>
          <ram:CountryID>{$this->esc($client?->country ?? 'FR')}</ram:CountryID>
        </ram:PostalTradeAddress>
      </ram:BuyerTradeParty>
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeDelivery/>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:InvoiceCurrencyCode>{$this->esc($currency)}</ram:InvoiceCurrencyCode>
      <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        <ram:TaxBasisTotalAmount>{$this->fmt($totalExcl)}</ram:TaxBasisTotalAmount>
        <ram:TaxTotalAmount currencyID="{$this->esc($currency)}">{$this->fmt($tax)}</ram:TaxTotalAmount>
        <ram:GrandTotalAmount>{$this->fmt($totalIncl)}</ram:GrandTotalAmount>
        <ram:DuePayableAmount>{$this->fmt($totalIncl)}</ram:DuePayableAmount>
      </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;

        return $xml;
    }

    /**
     * Génère le PDF/A-3 + embed XML. Retourne le path local du PDF.
     * NOTE : l'embedding XML dans PDF/A-3 nécessite une lib externe.
     * En MVP, on génère PDF + XML séparés ; en prod, intégrer:
     *   - "atgp/factur-x" (recommended) : composer require atgp/factur-x
     *   - OU FPDI/FPDF + manual /Filespec entry
     */
    public function buildPdf(FinanceInvoice $invoice): array
    {
        $xml = $this->buildXml($invoice);

        // Stocke XML séparé (en attendant embed PDF/A-3 lib)
        $disk = config('accounting_v2.export_disk', 'local');
        $path = ('exports/einvoicing/' . now()->format('Y/m/d') . '/' . $invoice->id);
        \Illuminate\Support\Facades\Storage::disk($disk)->put($path . '.xml', $xml);

        // PDF visuel humain via DomPDF (déjà installé)
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            try {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('emails.invoice', ['invoice' => $invoice]);
                $pdfContent = $pdf->output();
                \Illuminate\Support\Facades\Storage::disk($disk)->put($path . '.pdf', $pdfContent);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[factur_x] PDF gen failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'xml_path' => $path . '.xml',
            'pdf_path' => $path . '.pdf',
            'disk' => $disk,
            'standard' => 'Factur-X 1.0.06 MINIMUM',
            'note' => 'XML CII et PDF générés séparément. Pour PDF/A-3 avec XML embedded, installer atgp/factur-x.',
        ];
    }

    protected function esc(string $val): string
    {
        return htmlspecialchars($val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function fmt(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
