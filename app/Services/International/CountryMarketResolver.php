<?php

namespace App\Services\International;

use App\Models\Booking;
use App\Models\Country;
use App\Models\CountryServiceCatalogRule;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\International\DeviseParPays;
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

    /**
     * LA DEVISE D'UNE COMMANDE QU'ON EST EN TRAIN DE CREER, EN UN SEUL APPEL.
     *
     * Il existait TROIS reponses a cette question, selon le chemin emprunte :
     *
     *   `CreateBookingAction`         le marche-pays -- la bonne
     *   `CreateBookingFromApiAction`  `preferred_currency` du COMPTE client
     *   `CreateBookingTool`           `'EUR'` en dur
     *
     * La deuxieme est la plus trompeuse : une preference de compte n'est pas une position. Un
     * client dont le profil dit « EUR » commandant un menage a Casablanca obtenait une reservation
     * en euros pour un service paye en dirhams -- et le prix, lui, venait bien du marche marocain.
     * Les deux nombres decrivaient des monnaies differentes sans que rien ne le signale.
     *
     * Cette methode donne UNE reponse, tiree de la position : site, zone, code postal, puis le code
     * pays de l'adresse saisie quand aucune table ne couvre encore le marche. C'est le meme
     * enchainement que pour une reservation existante, offert aux appelants qui n'en ont pas encore.
     */
    public function deviseAttendue(
        ?User $client = null,
        ?PostalCode $postalCode = null,
        ?ServiceZone $zone = null,
        ?string $isoPays = null,
    ): string {
        $country = $this->resolveCountry($client, $postalCode, $zone);

        if (! $country && $isoPays !== null) {
            $code = strtoupper(trim($isoPays));

            $country = strlen($code) === 2
                ? Country::query()->where('iso_code', $code)->first()
                : null;

            /*
             * AUCUNE FICHE PAYS N'EXISTE ENCORE POUR CE MARCHE.
             *
             * On repond quand meme depuis la table ISO plutot que de retomber sur la devise de
             * base : c'est le cas d'un pays tout juste ouvert, ou d'une adresse saisie hors des
             * zones deja maillees. Ne rien deduire ici libellerait la commande en euros, ce qui
             * est precisement le defaut corrige.
             */
            if (! $country) {
                $deduite = DeviseParPays::pour($code);

                if ($deduite !== null) {
                    return $deduite;
                }
            }
        }

        return $this->effectiveCurrency($this->buildMarketContext($country, null));
    }

    public function resolveForRendezVous(Booking $rendezVous): array
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
            ?: $rendezVous->client?->organizationAccount?->country
            /*
             * DERNIER RECOURS : LE PAYS ECRIT SUR LA RESERVATION ELLE-MEME.
             *
             * `bookings.country` porte le code ISO de l'adresse saisie. Toutes les pistes ci-dessus
             * passent par une TABLE -- site, zone, code postal, organisation -- et rendent donc
             * `null` sur un marche tout juste ouvert, ou l'adresse existe avant le maillage
             * geographique. Le contexte retombait alors sur la devise de base, c'est-a-dire l'euro,
             * pour une commande passee au Maroc.
             *
             * C'est bien la POSITION qu'on lit, simplement sous sa forme la plus brute : ce que le
             * client a saisi. On la place en dernier parce qu'un texte se trompe plus facilement
             * qu'une ligne de reference.
             */
            ?: $this->paysDeLAdresse($rendezVous);

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

    /**
     * LA DEVISE SUIT LA POSITION, ET NE RETOMBE SUR L'EURO QU'EN DERNIER.
     *
     * Le repli etait `'EUR'` en dur juste apres la fiche pays. Un pays ouvert sans devise
     * renseignee -- le formulaire d'administration proposait `EUR` par defaut, quel que soit le
     * pays -- libellait donc en euros des commandes passees au Maroc, et rien ne le disait.
     *
     * L'ordre va du plus explicite au plus deduit :
     *
     *   1. le profil de facturation, quand la plateforme a choisi une devise pour ce marche ;
     *   2. la devise posee sur la fiche pays, que l'administration peut corriger ;
     *   3. la table ISO 3166 -> 4217, qui sait que le Maroc paie en dirhams ;
     *   4. la devise de base de la plateforme, et seulement la.
     *
     * Le cran 3 est celui qui manquait. Il ne prend jamais le pas sur une valeur posee : il repond
     * quand personne n'a repondu.
     */
    public function effectiveCurrency(array $context): string
    {
        $posee = data_get($context['billing_profile'] ?? null, 'currency_code')
            ?: data_get($context['country'] ?? null, 'currency_code');

        if (is_string($posee) && trim($posee) !== '') {
            return strtoupper(trim($posee));
        }

        $deduite = DeviseParPays::pour((string) data_get($context['country'] ?? null, 'iso_code'));

        return $deduite ?? strtoupper((string) config('fx.base_currency', 'EUR'));
    }

    public function effectiveTaxRate(array $context, ?Booking $rendezVous = null): float
    {
        $accountMetadata = (array) ($rendezVous?->organizationAccount?->metadata ?? []);

        return round((float) (
            data_get($context['billing_profile'] ?? null, 'default_tax_rate')
            ?: data_get($context['operational_setting'] ?? null, 'default_tax_rate')
            ?: Arr::get($accountMetadata, 'finance.tax_rate')
            ?: 21.0
        ), 2);
    }

    public function paymentTermsDays(array $context, ?Booking $rendezVous = null): int
    {
        $accountMetadata = (array) ($rendezVous?->organizationAccount?->metadata ?? []);

        return (int) (
            data_get($context['billing_profile'] ?? null, 'payment_terms_days')
            ?: Arr::get($accountMetadata, 'finance.payment_terms_days')
            ?: ($rendezVous?->organization_account_id ? 30 : 14)
        );
    }

    public function quoteValidityDays(array $context, ?Booking $rendezVous = null): int
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

    /**
     * La fiche pays correspondant au code ISO porte par la reservation, si elle existe.
     *
     * On ne FABRIQUE pas de pays : rendre `null` laisse `effectiveCurrency()` faire son propre
     * repli, qui sait, lui, consulter la table ISO. Creer une ligne a la volee depuis une saisie
     * client peuplerait le catalogue geographique de pays qu'aucun administrateur n'a ouverts.
     */
    protected function paysDeLAdresse(Booking $rendezVous): ?Country
    {
        $code = strtoupper(trim((string) ($rendezVous->country ?? '')));

        if (strlen($code) !== 2) {
            return null;
        }

        return Country::query()->where('iso_code', $code)->first();
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
