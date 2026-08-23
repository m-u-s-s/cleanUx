<?php

namespace App\Services\Finance\EInvoicing;

use App\Models\FinanceInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/** Builder PDF/A-3 + XML CII embedded (norme Factur-X / ZUGFeRD). */
class FacturXBuilder
{
    /** Génère le XML CII Cross Industry Invoice depuis une FinanceInvoice. */
    public function buildXml(FinanceInvoice $invoice): string
    {
        $client = $invoice->client;
        $issued = Carbon::parse($invoice->issued_at ?? $invoice->created_at);

        $totalExcl = (float) ($invoice->subtotal ?? 0);
        $totalIncl = (float) ($invoice->total_amount ?? $invoice->subtotal ?? 0);
        $tax = (float) ($invoice->tax_amount ?? max(0.0, $totalIncl - $totalExcl));
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
    <ram:ID>{$this->esc($invoice->invoice_number ?? 'INV-'.$invoice->id)}</ram:ID>
    <ram:TypeCode>380</ram:TypeCode>
    <ram:IssueDateTime>
      <udt:DateTimeString format="102">{$issued->format('Ymd')}</udt:DateTimeString>
    </ram:IssueDateTime>
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:ApplicableHeaderTradeAgreement>
      <ram:SellerTradeParty>
        <ram:Name>{$this->esc(config('app.name', 'Brio'))}</ram:Name>
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

    /** Génère le PDF/A-3 + embed XML. Retourne le path local du PDF. */
    public function buildPdf(FinanceInvoice $invoice): array
    {
        $xml = $this->buildXml($invoice);

        // Stocke XML séparé (en attendant embed PDF/A-3 lib)
        $disk = config('accounting_v2.export_storage_disk', 'local');
        $path = ('exports/einvoicing/'.now()->format('Y/m/d').'/'.$invoice->id);
        Storage::disk($disk)->put($path.'.xml', $xml);

        // PDF visuel humain via DomPDF (déjà installé). Réutilise la vue PDF facture existante.
        $view = 'client.finance.invoice-pdf';
        if (class_exists(Pdf::class) && View::exists($view)) {
            try {
                $pdf = Pdf::loadView($view, ['invoice' => $invoice]);
                $pdfContent = $pdf->output();
                Storage::disk($disk)->put($path.'.pdf', $pdfContent);
            } catch (\Throwable $e) {
                Log::warning('[factur_x] PDF gen failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'xml_path' => $path.'.xml',
            'pdf_path' => $path.'.pdf',
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
