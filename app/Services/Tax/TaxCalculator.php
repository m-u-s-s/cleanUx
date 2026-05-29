<?php

namespace App\Services\Tax;

/**
 * Multi-country EU VAT calculator.
 *
 * Provides standard VAT rates for supported European countries.
 * Used for invoice generation, pricing display, and accounting exports.
 */
class TaxCalculator
{
    /**
     * Standard VAT rates per EU country code (ISO 3166-1 alpha-2).
     * Rates as of 2024 — update when legislation changes.
     */
    private const VAT_RATES = [
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

    /**
     * Default fallback rate when country not found (Belgium standard).
     */
    private const FALLBACK_RATE = 0.21;

    /**
     * Calculate VAT for a given amount and country.
     *
     * @param  float  $amountExclVat  Amount before VAT in major currency units (e.g. EUR)
     * @param  string  $countryCode  ISO 3166-1 alpha-2 country code
     * @return array{
     *   amount_excl_vat: float,
     *   vat_rate: float,
     *   vat_amount: float,
     *   amount_incl_vat: float,
     *   country_code: string
     * }
     */
    public function calculateVat(float $amountExclVat, string $countryCode): array
    {
        $code = strtoupper(trim($countryCode));
        $rate = self::VAT_RATES[$code] ?? self::FALLBACK_RATE;

        $vatAmount = round($amountExclVat * $rate, 2);

        return [
            'amount_excl_vat' => $amountExclVat,
            'vat_rate' => $rate,
            'vat_amount' => $vatAmount,
            'amount_incl_vat' => round($amountExclVat + $vatAmount, 2),
            'country_code' => $code,
        ];
    }

    /**
     * Get the VAT rate for a country (returns fallback for unsupported countries).
     */
    public function getVatRate(string $countryCode): float
    {
        return self::VAT_RATES[strtoupper($countryCode)] ?? self::FALLBACK_RATE;
    }

    /**
     * List of ISO codes with configured rates.
     *
     * @return string[]
     */
    public function supportedCountries(): array
    {
        return array_keys(self::VAT_RATES);
    }

    /**
     * Convert a VAT-inclusive amount to the excl-VAT equivalent.
     */
    public function extractVat(float $amountInclVat, string $countryCode): array
    {
        $code = strtoupper(trim($countryCode));
        $rate = self::VAT_RATES[$code] ?? self::FALLBACK_RATE;

        $amountExcl = round($amountInclVat / (1 + $rate), 2);
        $vatAmount = round($amountInclVat - $amountExcl, 2);

        return [
            'amount_excl_vat' => $amountExcl,
            'vat_rate' => $rate,
            'vat_amount' => $vatAmount,
            'amount_incl_vat' => $amountInclVat,
            'country_code' => $code,
        ];
    }
}
