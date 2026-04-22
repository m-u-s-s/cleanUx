<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\CountryBillingProfile;
use App\Models\CountryOperationalSetting;
use App\Models\CountryServiceCatalogRule;
use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Services\Finance\FinanceDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDocumentServiceInternationalRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_country_billing_profile_for_quote_and_invoice(): void
    {
        $country = Country::factory()->create(['iso_code' => 'US', 'currency_code' => 'USD', 'name' => 'United States']);

        CountryOperationalSetting::query()->create([
            'country_id' => $country->id,
            'booking_enabled' => true,
            'billing_enabled' => true,
            'market_stage' => 'billing_enabled',
            'currency_symbol' => '$',
            'date_format' => 'm/d/Y',
            'default_tax_rate' => 8.25,
        ]);

        CountryBillingProfile::query()->create([
            'country_id' => $country->id,
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'quote_prefix' => 'QUS',
            'invoice_prefix' => 'IUS',
            'tax_label' => 'Sales tax',
            'default_tax_rate' => 8.25,
            'payment_terms_days' => 21,
            'quote_validity_days' => 10,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'currency_position' => 'before',
        ]);

        $catalog = ServiceCatalog::factory()->create(['base_price' => 100]);
        CountryServiceCatalogRule::query()->create([
            'country_id' => $country->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
        ]);

        $zone = ServiceZone::factory()->create(['country_id' => $country->id]);
        $postal = PostalCode::factory()->create(['country_id' => $country->id]);
        $rdv = RendezVous::factory()->create([
            'service_catalog_id' => $catalog->id,
            'service_zone_id' => $zone->id,
            'postal_code_id' => $postal->id,
            'status' => 'termine',
            'devis_estime' => 100,
            'pricing_snapshot' => ['devis_estime' => 100],
        ]);

        $service = app(FinanceDocumentService::class);
        $quote = $service->syncQuoteForRendezVous($rdv->fresh(['serviceCatalog', 'serviceZone', 'postalCode']));
        $invoice = $service->syncInvoiceForRendezVous($rdv->fresh(['serviceCatalog', 'serviceZone', 'postalCode']));

        $this->assertStringStartsWith('QUS-', $quote->quote_number);
        $this->assertStringStartsWith('IUS-', $invoice->invoice_number);
        $this->assertSame('USD', $quote->currency);
        $this->assertSame(8.25, (float) $quote->tax_rate);
        $this->assertSame('$', data_get($quote->snapshot, 'document_formatting.currency_symbol'));
        $this->assertSame('Sales tax', data_get($invoice->snapshot, 'document_formatting.tax_label'));
    }
}
