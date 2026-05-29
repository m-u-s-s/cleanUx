<?php

namespace Tests\Unit;

use App\Services\Country\CountryConfigService;
use App\Services\Tax\TaxCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for core business-logic services.
 * No Laravel boot — no DB — instant feedback.
 */
class BusinessLogicUnitTest extends TestCase
{
    // -----------------------------------------------------------------------
    // TaxCalculator
    // -----------------------------------------------------------------------

    public function test_tax_calculator_all_supported_countries(): void
    {
        $calc = new TaxCalculator;

        $expected = [
            'BE' => 0.21,
            'FR' => 0.20,
            'NL' => 0.21,
            'DE' => 0.19,
            'ES' => 0.21,
            'IT' => 0.22,
            'PT' => 0.23,
            'LU' => 0.17,
            'AT' => 0.20,
        ];

        foreach ($expected as $code => $rate) {
            $result = $calc->calculateVat(100.00, $code);

            $this->assertSame($rate, $result['vat_rate'], "VAT rate mismatch for {$code}");
            $this->assertSame(round(100 * $rate, 2), $result['vat_amount'], "VAT amount mismatch for {$code}");
            $this->assertSame(round(100 + 100 * $rate, 2), $result['amount_incl_vat'], "Total incl VAT mismatch for {$code}");
            $this->assertSame(100.0, $result['amount_excl_vat'], "Excl amount mutated for {$code}");
            $this->assertSame($code, $result['country_code'], "Country code mismatch for {$code}");
        }
    }

    public function test_tax_calculator_unknown_country_defaults_to_21_percent(): void
    {
        $calc = new TaxCalculator;
        $result = $calc->calculateVat(100.00, 'XX');

        $this->assertSame(0.21, $result['vat_rate']);
        $this->assertSame(21.0, $result['vat_amount']);
        $this->assertSame(121.0, $result['amount_incl_vat']);
    }

    public function test_tax_calculator_lowercase_country_code_normalised(): void
    {
        $calc = new TaxCalculator;
        $result = $calc->calculateVat(200.00, 'de');

        $this->assertSame(0.19, $result['vat_rate']);
        $this->assertSame('DE', $result['country_code']);
    }

    public function test_tax_calculator_extract_vat_round_trips(): void
    {
        $calc = new TaxCalculator;

        foreach (['BE', 'FR', 'IT', 'LU'] as $code) {
            $forward = $calc->calculateVat(100.00, $code);
            $backward = $calc->extractVat($forward['amount_incl_vat'], $code);

            // Allow ±0.01 for rounding; the two paths must agree on the rate.
            $this->assertSame($forward['vat_rate'], $backward['vat_rate'], "Rate mismatch on extract for {$code}");
            $this->assertEqualsWithDelta(100.00, $backward['amount_excl_vat'], 0.01, "Round-trip failed for {$code}");
        }
    }

    public function test_tax_calculator_get_vat_rate_returns_float(): void
    {
        $calc = new TaxCalculator;
        $this->assertSame(0.22, $calc->getVatRate('IT'));
        $this->assertSame(0.21, $calc->getVatRate('ZZ')); // unknown → fallback
    }

    public function test_tax_calculator_supported_countries_lists_all_nine(): void
    {
        $calc = new TaxCalculator;
        $this->assertCount(9, $calc->supportedCountries());
    }

    // -----------------------------------------------------------------------
    // CountryConfigService
    // -----------------------------------------------------------------------

    public function test_country_config_supported_returns_all_nine_countries(): void
    {
        $service = new CountryConfigService;
        $supported = $service->supported();

        $this->assertCount(9, $supported);

        foreach (['BE', 'FR', 'NL', 'DE', 'ES', 'IT', 'PT', 'LU', 'AT'] as $code) {
            $this->assertContains($code, $supported, "{$code} missing from supported list");
        }
    }

    public function test_country_config_returns_correct_data_for_nl(): void
    {
        $service = new CountryConfigService;
        $nl = $service->get('NL');

        $this->assertSame('Pays-Bas', $nl['name']);
        $this->assertSame(0.21, $nl['vat_rate']);
        $this->assertSame('+31', $nl['phone_prefix']);
        $this->assertContains('driving_licence', $nl['kyc_docs']);
        $this->assertSame('Europe/Amsterdam', $nl['timezone']);
        $this->assertSame('EUR', $nl['currency']);
    }

    public function test_country_config_unknown_code_defaults_to_belgium(): void
    {
        $service = new CountryConfigService;
        $fallback = $service->get('ZZ');

        $this->assertSame('Belgique', $fallback['name']);
        $this->assertSame(0.21, $fallback['vat_rate']);
        $this->assertSame('+32', $fallback['phone_prefix']);
    }

    public function test_country_config_get_vat_rate_helper(): void
    {
        $service = new CountryConfigService;

        $this->assertSame(0.19, $service->getVatRate('DE'));
        $this->assertSame(0.23, $service->getVatRate('PT'));
        $this->assertSame(0.17, $service->getVatRate('LU'));
    }

    public function test_country_config_lowercase_code_normalised(): void
    {
        $service = new CountryConfigService;
        $fr = $service->get('fr');

        $this->assertSame('France', $fr['name']);
        $this->assertSame(0.20, $fr['vat_rate']);
    }

    public function test_country_config_all_returns_nine_entries(): void
    {
        $service = new CountryConfigService;
        $this->assertCount(9, $service->all());
    }

    public function test_country_config_kyc_docs_are_arrays(): void
    {
        $service = new CountryConfigService;
        foreach ($service->all() as $code => $config) {
            $this->assertIsArray($config['kyc_docs'], "kyc_docs must be array for {$code}");
            $this->assertNotEmpty($config['kyc_docs'], "kyc_docs empty for {$code}");
        }
    }
}
