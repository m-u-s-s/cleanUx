<?php

namespace App\Services\Payments;

/** StripeCountryMapper — per-country Stripe Connect account configuration. */
class StripeCountryMapper
{
    private const SUPPORTED_COUNTRIES = [
        'BE' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers'],
        ],
        'FR' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers'],
        ],
        'NL' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers', 'ideal_payments'],
        ],
        'DE' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers', 'sepa_debit_payments'],
        ],
        'ES' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers'],
        ],
        'IT' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers'],
        ],
        'PT' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers'],
        ],
        'LU' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers'],
        ],
        'AT' => [
            'currency' => 'eur',
            'capabilities' => ['card_payments', 'transfers'],
        ],
    ];

    /** Default country when the requested code is unsupported. */
    private const FALLBACK_COUNTRY = 'BE';

    /**
     * Return full config for a country (currency + capabilities array).
     *
     * @return array{currency: string, capabilities: string[]}
     */
    public function getConfig(string $countryCode): array
    {
        $code = strtoupper(trim($countryCode));

        return self::SUPPORTED_COUNTRIES[$code] ?? self::SUPPORTED_COUNTRIES[self::FALLBACK_COUNTRY];
    }

    /**
     * @return string[]
     */
    public function supportedCountries(): array
    {
        return array_keys(self::SUPPORTED_COUNTRIES);
    }

    /**
     * Build the Stripe-formatted capabilities object (each capability → ['requested' => true]).
     *
     * @return array<string, array{requested: bool}>
     */
    public function getCapabilities(string $countryCode): array
    {
        $config = $this->getConfig($countryCode);

        return collect($config['capabilities'])
            ->mapWithKeys(fn (string $cap) => [$cap => ['requested' => true]])
            ->all();
    }

    /** Settlement currency for a country. */
    public function getCurrency(string $countryCode): string
    {
        return $this->getConfig($countryCode)['currency'];
    }

    /** Whether a country supports a specific payment method capability. */
    public function supportsCapability(string $countryCode, string $capability): bool
    {
        return in_array($capability, $this->getConfig($countryCode)['capabilities'], true);
    }
}
