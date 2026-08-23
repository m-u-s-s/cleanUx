<?php

namespace Tests\Unit;

use App\Services\Country\CountryConfigService;
use App\Services\Tax\TaxCalculator;
use PHPUnit\Framework\TestCase;

/** Pure unit tests for core business-logic services. No Laravel boot — no DB — instant feedback. */
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

        // CINQ VALEURS PAR PAYS, ET LA TABLE ENTIERE D'UN COUP.
        $ecarts = [];

        foreach ($expected as $code => $rate) {
            $r = $calc->calculateVat(100.00, $code);

            foreach ([
                'vat_rate' => $rate,
                'vat_amount' => round(100 * $rate, 2),
                'amount_incl_vat' => round(100 + 100 * $rate, 2),
                'amount_excl_vat' => 100.0,
                'country_code' => $code,
            ] as $champ => $attendu) {
                if ($r[$champ] !== $attendu) {
                    $ecarts[] = sprintf('%s.%s : attendu %s, obtenu %s', $code, $champ,
                        var_export($attendu, true), var_export($r[$champ], true));
                }
            }
        }

        $this->assertSame([], $ecarts, 'La grille de TVA ne rend pas les valeurs attendues.');
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

        $ecarts = [];

        foreach (['BE', 'FR', 'IT', 'LU'] as $code) {
            $aller = $calc->calculateVat(100.00, $code);
            $retour = $calc->extractVat($aller['amount_incl_vat'], $code);

            if ($aller['vat_rate'] !== $retour['vat_rate']) {
                $ecarts[] = "{$code} : taux aller {$aller['vat_rate']}, taux retour {$retour['vat_rate']}";
            }

            // Un centime tolere pour l'arrondi ; au-dela, les deux chemins ne s'accordent plus.
            if (abs(100.00 - $retour['amount_excl_vat']) > 0.01) {
                $ecarts[] = "{$code} : aller-retour rend {$retour['amount_excl_vat']} au lieu de 100,00";
            }
        }

        $this->assertSame([], $ecarts, 'Les deux chemins de TVA ne concordent pas.');
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

        $manquants = array_values(array_diff(
            ['BE', 'FR', 'NL', 'DE', 'ES', 'IT', 'PT', 'LU', 'AT'],
            $supported,
        ));

        $this->assertSame([], $manquants, 'Ces pays manquent a la liste supportee.');
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
        $defauts = [];

        foreach ($service->all() as $code => $config) {
            if (! is_array($config['kyc_docs'] ?? null)) {
                $defauts[] = "{$code} : kyc_docs n'est pas un tableau";
            } elseif ($config['kyc_docs'] === []) {
                $defauts[] = "{$code} : kyc_docs vide, aucune piece ne serait demandee";
            }
        }

        $this->assertSame([], $defauts, 'Ces pays n exigent aucune piece d identite.');
    }
}
