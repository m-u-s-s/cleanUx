<?php

namespace Tests\Unit\Finance;

use App\Models\FinanceInvoice;
use App\Models\User;
use App\Services\Finance\EInvoicing\FacturXBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacturXBuilderCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_a_well_formed_cii_xml_from_the_real_invoice_columns(): void
    {
        $client = User::factory()->create(['name' => 'ACME Corp']);
        $invoice = FinanceInvoice::factory()->create([
            'client_id' => $client->id,
            'invoice_number' => 'FAC-2026-0001',
            'currency' => 'EUR',
            'subtotal' => 100.00,
            'tax_amount' => 21.00,
            'total_amount' => 121.00,
            'issued_at' => now(),
        ]);

        $xml = app(FacturXBuilder::class)->buildXml($invoice);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('urn:factur-x.eu:1p0:minimum', $xml);
        $this->assertStringContainsString('<ram:TypeCode>380</ram:TypeCode>', $xml);
        // P0.5: reads the real columns (invoice_number, client relation, subtotal/tax/total).
        $this->assertStringContainsString('<ram:ID>FAC-2026-0001</ram:ID>', $xml);
        $this->assertStringContainsString('<ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>', $xml);
        $this->assertStringContainsString('<ram:Name>ACME Corp</ram:Name>', $xml);
        $this->assertStringContainsString('<ram:TaxBasisTotalAmount>100.00</ram:TaxBasisTotalAmount>', $xml);
        $this->assertStringContainsString('currencyID="EUR">21.00</ram:TaxTotalAmount>', $xml);
        $this->assertStringContainsString('<ram:GrandTotalAmount>121.00</ram:GrandTotalAmount>', $xml);

        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($xml), 'generated CII XML must be valid XML');
    }

    #[Test]
    public function it_falls_back_to_generated_reference_when_invoice_number_is_blank(): void
    {
        $invoice = FinanceInvoice::factory()->create(['currency' => 'EUR']);
        $invoice->forceFill(['invoice_number' => null])->saveQuietly();

        $xml = app(FacturXBuilder::class)->buildXml($invoice->fresh());

        $this->assertStringContainsString('<ram:ID>INV-'.$invoice->id.'</ram:ID>', $xml);
    }

    #[Test]
    public function it_writes_the_xml_artifact_and_returns_the_export_descriptor(): void
    {
        Storage::fake('local');

        $invoice = FinanceInvoice::factory()->create();

        $result = app(FacturXBuilder::class)->buildPdf($invoice);

        $this->assertSame('local', $result['disk']);
        $this->assertStringEndsWith('.xml', $result['xml_path']);
        $this->assertStringContainsString('exports/einvoicing/', $result['xml_path']);

        // The CII XML is always persisted, independent of PDF generation.
        Storage::disk('local')->assertExists($result['xml_path']);
        $this->assertStringContainsString('rsm:CrossIndustryInvoice', Storage::disk('local')->get($result['xml_path']));
    }
}
