<?php

namespace App\Services\Country;

use App\Models\Country;
use App\Support\International\Devise;
use Illuminate\Support\Facades\Schema;

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

    /**
     * LA TABLE EN DUR N'EST PLUS LA VERITE : ELLE EST LE SOCLE.
     *
     * Ces neuf pays europeens etaient TOUTE la reponse. `/api/countries` les servait tels quels,
     * et c'est ce que lisent les selecteurs de pays des applications. Ajouter le Maroc dans
     * `/admin` creait bien la fiche, ouvrait bien les zones, posait bien MAD -- et le pays
     * n'apparaissait NULLE PART cote client. Le catalogue geographique et cette liste decrivaient
     * deux mondes differents.
     *
     * On lit donc la table `countries`, et la constante ne sert plus qu'a completer ce que la base
     * ne dit pas : taux de TVA de reference, documents d'identite acceptes, pays Stripe. Ces
     * champs-la n'ont pas de colonne, et les inventer serait pire que les tenir ici.
     *
     * LE SOCLE RESTE LE REPLI COMPLET quand la base est vide -- installation neuve, tests. Sans
     * cela, `/api/countries` rendrait un tableau vide et les selecteurs se videraient.
     */
    public function get(string $code): array
    {
        $code = strtoupper(trim($code));
        $tout = $this->all();

        return $tout[$code] ?? ($tout['BE'] ?? self::COUNTRIES['BE']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $enBase = $this->depuisLaBase();

        return $enBase === [] ? self::COUNTRIES : $enBase;
    }

    /**
     * @return list<string>
     */
    public function supported(): array
    {
        return array_keys($this->all());
    }

    /**
     * Les pays ACTIFS du catalogue, enrichis de ce que la base ne porte pas.
     *
     * `is_active` fait foi : un pays cree mais pas encore ouvert n'a rien a faire dans un selecteur
     * client -- c'est la meme regle que `CountryCenter`, qui cree tout pays ferme par defaut pour
     * qu'une faute de frappe ne rende pas un marche commandable.
     *
     * @return array<string, array<string, mixed>>
     */
    private function depuisLaBase(): array
    {
        try {
            if (! Schema::hasTable('countries')) {
                return [];
            }

            $pays = Country::query()->where('is_active', true)->get();
        } catch (\Throwable $e) {
            // Cette liste est servie par une route PUBLIQUE et mise en cache une heure : une base
            // indisponible doit degrader vers le socle, jamais rendre 500 sur la page d'accueil.
            return [];
        }

        $sortie = [];

        foreach ($pays as $ligne) {
            $code = strtoupper((string) $ligne->iso_code);
            $socle = self::COUNTRIES[$code] ?? [];

            $sortie[$code] = array_merge($socle, array_filter([
                'name' => $ligne->name,
                'currency' => Devise::normaliser($ligne->currency_code),
                'locale' => $ligne->default_locale,
                'phone_prefix' => $ligne->phone_code,
                'timezone' => $ligne->timezone,
            ], static fn ($valeur) => $valeur !== null && $valeur !== ''));

            /*
             * LES CHAMPS QUE LA BASE NE PORTE PAS, ET QU'ON NE DOIT PAS INVENTER.
             *
             * Un pays ouvert qui ne figure pas dans le socle n'a ni taux de TVA de reference, ni
             * liste de documents, ni pays Stripe. On pose des valeurs NEUTRES et explicites plutot
             * que de recopier celles de la Belgique : un taux belge applique au Maroc serait un
             * faux qui a l'air d'une donnee.
             */
            $sortie[$code] += [
                'vat_rate' => 0.0,
                'kyc_provider' => null,
                'kyc_docs' => ['identity_card', 'passport'],
                'stripe_country' => $code,
                'date_format' => 'd/m/Y',
                'distance_unit' => 'km',
            ];
        }

        return $sortie;
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
