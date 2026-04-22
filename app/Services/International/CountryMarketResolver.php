<?php

namespace App\Services\International;

use App\Models\Country;
use App\Models\CountryBillingProfile;
use App\Models\CountryOperationalSetting;
use App\Models\CountryServiceCatalogRule;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Support\Arr;

class CountryMarketResolver
{
    public function resolveForBooking(
        ?User $client,
        ?PostalCode $postalCode = null,
        ?ServiceZone $zone = null,
        ?OrganizationSite $organizationSite = null,
        ?ServiceCatalog $catalog = null,
    ): array {
        $country = $this->resolveCountry($client, $postalCode, $zone, $organizationSite);

        return $this->buildMarketContext($country, $catalog);
    }

    public function resolveForRendezVous(RendezVous $rendezVous): array
    {
        $rendezVous->loadMissing([
            'client.organizationAccount.country',
            'organizationAccount.country',
            'organizationSite.organizationAccount.country',
            'organizationSite.postalCodeReference.country',
            'postalCode.country',
            'serviceZone.country',
            'serviceCatalog',
        ]);

        $country = $rendezVous->organizationSite?->organizationAccount?->country
            ?: $rendezVous->organizationAccount?->country
            ?: $rendezVous->organizationSite?->postalCodeReference?->country
            ?: $rendezVous->serviceZone?->country
            ?: $rendezVous->postalCode?->country
            ?: $rendezVous->client?->organizationAccount?->country;

        return $this->buildMarketContext($country, $rendezVous->serviceCatalog);
    }

    public function bookingEnabled(array $context): bool
    {
        $setting = $context['operational_setting'] ?? null;

        return $setting ? (bool) $setting->booking_enabled : true;
    }

    public function billingEnabled(array $context): bool
    {
        $setting = $context['operational_setting'] ?? null;

        return $setting ? (bool) $setting->billing_enabled : true;
    }

    public function serviceEnabled(array $context): bool
    {
        $rule = $context['service_rule'] ?? null;

        return $rule ? (bool) $rule->is_enabled : true;
    }

    public function requiresManualValidation(array $context): bool
    {
        return (bool) data_get($context['service_rule'] ?? null, 'requires_manual_validation', false);
    }

    public function requiresQuote(array $context): bool
    {
        return (bool) data_get($context['service_rule'] ?? null, 'requires_quote', false);
    }

    public function minimumNoticeHours(array $context): int
    {
        return (int) data_get($context['service_rule'] ?? null, 'minimum_notice_hours', 0);
    }

    public function countryPriceMultiplier(array $context): float
    {
        $multiplier = (float) data_get($context['service_rule'] ?? null, 'price_multiplier', 1.0);

        return $multiplier > 0 ? $multiplier : 1.0;
    }

    public function effectiveCurrency(array $context): string
    {
        return (string) (
            data_get($context['billing_profile'] ?? null, 'currency_code')
            ?: data_get($context['country'] ?? null, 'currency_code')
            ?: 'EUR'
        );
    }

    public function effectiveTaxRate(array $context, ?RendezVous $rendezVous = null): float
    {
        $accountMetadata = (array) ($rendezVous?->organizationAccount?->metadata ?? []);

        return round((float) (
            data_get($context['billing_profile'] ?? null, 'default_tax_rate')
            ?: data_get($context['operational_setting'] ?? null, 'default_tax_rate')
            ?: Arr::get($accountMetadata, 'finance.tax_rate')
            ?: 21.0
        ), 2);
    }

    public function paymentTermsDays(array $context, ?RendezVous $rendezVous = null): int
    {
        $accountMetadata = (array) ($rendezVous?->organizationAccount?->metadata ?? []);

        return (int) (
            data_get($context['billing_profile'] ?? null, 'payment_terms_days')
            ?: Arr::get($accountMetadata, 'finance.payment_terms_days')
            ?: ($rendezVous?->organization_account_id ? 30 : 14)
        );
    }

    public function quoteValidityDays(array $context, ?RendezVous $rendezVous = null): int
    {
        $accountMetadata = (array) ($rendezVous?->organizationAccount?->metadata ?? []);

        return (int) (
            data_get($context['billing_profile'] ?? null, 'quote_validity_days')
            ?: Arr::get($accountMetadata, 'finance.quote_validity_days')
            ?: 15
        );
    }

    public function formatting(array $context): array
    {
        $currency = $this->effectiveCurrency($context);
        $symbol = (string) (
            data_get($context['billing_profile'] ?? null, 'currency_symbol')
            ?: data_get($context['operational_setting'] ?? null, 'currency_symbol')
            ?: match ($currency) {
                'USD' => '$',
                'GBP' => '£',
                'CHF' => 'CHF',
                default => '€',
            }
        );

        return [
            'currency' => $currency,
            'currency_symbol' => $symbol,
            'currency_position' => (string) (data_get($context['billing_profile'] ?? null, 'currency_position') ?: 'after'),
            'decimal_separator' => (string) (data_get($context['billing_profile'] ?? null, 'decimal_separator') ?: ','),
            'thousands_separator' => (string) (data_get($context['billing_profile'] ?? null, 'thousands_separator') ?: ' '),
            'date_format' => (string) (data_get($context['operational_setting'] ?? null, 'date_format') ?: 'd/m/Y'),
            'time_format' => (string) (data_get($context['operational_setting'] ?? null, 'time_format') ?: 'H:i'),
            'tax_label' => (string) (data_get($context['billing_profile'] ?? null, 'tax_label') ?: 'TVA'),
            'prices_include_tax' => (bool) data_get($context['billing_profile'] ?? null, 'display_prices_tax_inclusive', false),
        ];
    }

    public function marketStage(array $context): string
    {
        return (string) (data_get($context['operational_setting'] ?? null, 'market_stage') ?: 'legacy');
    }

    protected function buildMarketContext(?Country $country, ?ServiceCatalog $catalog): array
    {
        $country?->loadMissing(['operationalSetting', 'billingProfile']);

        $serviceRule = null;
        if ($country && $catalog) {
            $serviceRule = CountryServiceCatalogRule::query()
                ->where('country_id', $country->id)
                ->where('service_catalog_id', $catalog->id)
                ->first();
        }

        return [
            'country' => $country,
            'operational_setting' => $country?->operationalSetting,
            'billing_profile' => $country?->billingProfile,
            'service_rule' => $serviceRule,
        ];
    }

    protected function resolveCountry(
        ?User $client,
        ?PostalCode $postalCode = null,
        ?ServiceZone $zone = null,
        ?OrganizationSite $organizationSite = null,
    ): ?Country {
        if ($organizationSite) {
            $organizationSite->loadMissing(['organizationAccount.country', 'postalCodeReference.country']);
        }

        $client?->loadMissing(['organizationAccount.country']);
        $postalCode?->loadMissing('country');
        $zone?->loadMissing('country');

        return $organizationSite?->organizationAccount?->country
            ?: $organizationSite?->postalCodeReference?->country
            ?: $zone?->country
            ?: $postalCode?->country
            ?: $client?->organizationAccount?->country;
    }
}
