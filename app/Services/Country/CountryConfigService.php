<?php

namespace App\Services\Country;

class CountryConfigService
{
    private const COUNTRIES = [
        'BE' => ['name' => 'Belgique', 'currency' => 'EUR', 'vat_rate' => 0.21, 'locale' => 'fr', 'phone_prefix' => '+32', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport'], 'stripe_country' => 'BE', 'timezone' => 'Europe/Brussels', 'date_format' => 'd/m/Y', 'distance_unit' => 'km'],
        'FR' => ['name' => 'France', 'currency' => 'EUR', 'vat_rate' => 0.20, 'locale' => 'fr', 'phone_prefix' => '+33', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport', 'residence_permit'], 'stripe_country' => 'FR', 'timezone' => 'Europe/Paris', 'date_format' => 'd/m/Y', 'distance_unit' => 'km'],
        'NL' => ['name' => 'Pays-Bas', 'currency' => 'EUR', 'vat_rate' => 0.21, 'locale' => 'nl', 'phone_prefix' => '+31', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport', 'driving_licence'], 'stripe_country' => 'NL', 'timezone' => 'Europe/Amsterdam', 'date_format' => 'd-m-Y', 'distance_unit' => 'km'],
        'DE' => ['name' => 'Deutschland', 'currency' => 'EUR', 'vat_rate' => 0.19, 'locale' => 'de', 'phone_prefix' => '+49', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport'], 'stripe_country' => 'DE', 'timezone' => 'Europe/Berlin', 'date_format' => 'd.m.Y', 'distance_unit' => 'km'],
        'ES' => ['name' => 'España', 'currency' => 'EUR', 'vat_rate' => 0.21, 'locale' => 'es', 'phone_prefix' => '+34', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport', 'nie'], 'stripe_country' => 'ES', 'timezone' => 'Europe/Madrid', 'date_format' => 'd/m/Y', 'distance_unit' => 'km'],
        'IT' => ['name' => 'Italia', 'currency' => 'EUR', 'vat_rate' => 0.22, 'locale' => 'it', 'phone_prefix' => '+39', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport'], 'stripe_country' => 'IT', 'timezone' => 'Europe/Rome', 'date_format' => 'd/m/Y', 'distance_unit' => 'km'],
        'PT' => ['name' => 'Portugal', 'currency' => 'EUR', 'vat_rate' => 0.23, 'locale' => 'pt', 'phone_prefix' => '+351', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport'], 'stripe_country' => 'PT', 'timezone' => 'Europe/Lisbon', 'date_format' => 'd/m/Y', 'distance_unit' => 'km'],
        'LU' => ['name' => 'Luxembourg', 'currency' => 'EUR', 'vat_rate' => 0.17, 'locale' => 'fr', 'phone_prefix' => '+352', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport'], 'stripe_country' => 'LU', 'timezone' => 'Europe/Luxembourg', 'date_format' => 'd/m/Y', 'distance_unit' => 'km'],
        'AT' => ['name' => 'Österreich', 'currency' => 'EUR', 'vat_rate' => 0.20, 'locale' => 'de', 'phone_prefix' => '+43', 'kyc_provider' => 'onfido', 'kyc_docs' => ['identity_card', 'passport'], 'stripe_country' => 'AT', 'timezone' => 'Europe/Vienna', 'date_format' => 'd.m.Y', 'distance_unit' => 'km'],
    ];

    public function get(string $code): array
    {
        return self::COUNTRIES[strtoupper($code)] ?? self::COUNTRIES['BE'];
    }

    public function all(): array
    {
        return self::COUNTRIES;
    }

    public function supported(): array
    {
        return array_keys(self::COUNTRIES);
    }

    public function getVatRate(string $code): float
    {
        return $this->get($code)['vat_rate'];
    }

    public function getKycDocs(string $code): array
    {
        return $this->get($code)['kyc_docs'];
    }

    public function getStripeCountry(string $code): string
    {
        return $this->get($code)['stripe_country'];
    }

    public function getTimezone(string $code): string
    {
        return $this->get($code)['timezone'];
    }
}
