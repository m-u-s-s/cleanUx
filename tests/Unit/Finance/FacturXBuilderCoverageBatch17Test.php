<?php

namespace Tests\Unit\Finance;

use App\Models\FinanceInvoice;
use App\Services\Finance\EInvoicing\FacturXBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacturXBuilderCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_a_well_formed_cii_xml_with_seller_and_buyer_blocks(): void
    {
        $invoice = FinanceInvoice::factory()->create([
            'currency' => 'EUR',
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
        ]);

        $xml = app(FacturXBuilder::class)->buildXml($invoice);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('rsm:CrossIndustryInvoice', $xml);
        $this->assertStringContainsString('urn:factur-x.eu:1p0:minimum', $xml);
        $this->assertStringContainsString('<ram:TypeCode>380</ram:TypeCode>', $xml);
        $this->assertStringContainsString('<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>', $xml);
        $this->assertStringContainsString('<ram:Name>Client</ram:Name>', $xml);
        $this->assertStringContainsString('<ram:CountryID>FR</ram:CountryID>', $xml);

        // Issue date uses the YYYYMMDD format="102" string.
        $this->assertStringContainsString('format="102">'.now()->format('Ymd'), $xml);

        // The XML must be parseable by a real DOM parser.
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($xml), 'generated CII XML must be valid XML');
    }

    #[Test]
    public function it_falls_back_to_generated_reference_and_defaults_when_fields_are_absent(): void
    {
        // issued_at / due_at null exercises the created_at + addDays(30) fallback paths.
        $invoice = FinanceInvoice::factory()->create([
            'currency' => 'EUR',
            'issued_at' => null,
            'due_at' => null,
        ]);

        $xml = app(FacturXBuilder::class)->buildXml($invoice);

        // No "reference" attribute on the model -> INV-{id} fallback.
        $this->assertStringContainsString('<ram:ID>INV-'.$invoice->id.'</ram:ID>', $xml);
        $this->assertStringContainsString('<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>', $xml);

        // Amounts collapse to 0.00 because the model exposes no amount_* attributes.
        $this->assertStringContainsString('<ram:TaxBasisTotalAmount>0.00</ram:TaxBasisTotalAmount>', $xml);
        $this->assertStringContainsString('<ram:GrandTotalAmount>0.00</ram:GrandTotalAmount>', $xml);
    }

    #[Test]
    public function it_writes_the_xml_artifact_and_returns_the_export_descriptor(): void
    {
        Storage::fake('local');

        $invoice = FinanceInvoice::factory()->create();

        $result = app(FacturXBuilder::class)->buildPdf($invoice);

        $this->assertSame('local', $result['disk']);
        $this->assertSame('Factur-X 1.0.06 MINIMUM', $result['standard']);
        $this->assertStringEndsWith('.xml', $result['xml_path']);
        $this->assertStringEndsWith('.pdf', $result['pdf_path']);
        $this->assertStringContainsString('exports/einvoicing/', $result['xml_path']);
        $this->assertArrayHasKey('note', $result);

        // The CII XML is always persisted, independent of PDF generation.
        Storage::disk('local')->assertExists($result['xml_path']);
        $stored = Storage::disk('local')->get($result['xml_path']);
        $this->assertStringContainsString('rsm:CrossIndustryInvoice', $stored);
    }
}
