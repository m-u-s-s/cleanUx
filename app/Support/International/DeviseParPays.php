<?php

namespace App\Support\International;

/**
 * QUELLE DEVISE POUR QUEL PAYS — la réponse par défaut, dérivée de la position.
 *
 * CE QUE CE FICHIER RÉPARE. La devise d'une réservation venait de trois endroits différents : la
 * préférence du compte client (`CreateBookingFromApiAction`), le marché-pays (`CreateBookingAction`),
 * et `'EUR'` écrit en dur (l'assistant). Et le formulaire de création d'un pays proposait `EUR` quel
 * que soit le pays : ajouter le Maroc donnait des dirhams libellés en euros, sans que rien ne le
 * signale — un mensonge silencieux sur tout ce qui a été commandé là-bas.
 *
 * ── C'EST UN DÉFAUT, PAS UNE LOI ─────────────────────────────────────────────────────────────
 *
 * `Country::currency_code` reste l'AUTORITÉ : un administrateur peut la corriger, et certains cas
 * l'exigent — une plateforme peut facturer en euros depuis un pays qui n'y appartient pas. Cette
 * table dit seulement ce qu'il faut proposer quand personne n'a encore choisi, et sur quoi retomber
 * quand aucune fiche pays n'existe. Elle ne remplace jamais une valeur posée.
 *
 * ── POURQUOI PAS `intl` ──────────────────────────────────────────────────────────────────────
 *
 * `ResourceBundle` sait faire cette correspondance, mais l'extension `intl` n'est pas garantie ici :
 * ce dépôt s'est déjà fait prendre par `class_exists('NumberFormatter')` qui rend `true` grâce à un
 * polyfill puis LÈVE pour toute locale autre que l'anglais. Une table explicite ne ment pas sur ce
 * qu'elle sait.
 *
 * Les zones euro et franc CFA sont listées pays par pays, à dessein : « la zone euro » n'est pas une
 * donnée du système, et l'écrire en compréhension obligerait à maintenir une seconde liste.
 */
final class DeviseParPays
{
    /**
     * ISO 3166-1 alpha-2 → ISO 4217.
     *
     * @var array<string, string>
     */
    private const TABLE = [
        // Zone euro
        'AT' => 'EUR', 'BE' => 'EUR', 'CY' => 'EUR', 'DE' => 'EUR', 'EE' => 'EUR',
        'ES' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR', 'GR' => 'EUR', 'HR' => 'EUR',
        'IE' => 'EUR', 'IT' => 'EUR', 'LT' => 'EUR', 'LU' => 'EUR', 'LV' => 'EUR',
        'MT' => 'EUR', 'NL' => 'EUR', 'PT' => 'EUR', 'SI' => 'EUR', 'SK' => 'EUR',
        'MC' => 'EUR', 'AD' => 'EUR', 'SM' => 'EUR', 'VA' => 'EUR',

        // Europe hors zone euro
        'BG' => 'BGN', 'CH' => 'CHF', 'CZ' => 'CZK', 'DK' => 'DKK', 'GB' => 'GBP',
        'HU' => 'HUF', 'IS' => 'ISK', 'LI' => 'CHF', 'NO' => 'NOK', 'PL' => 'PLN',
        'RO' => 'RON', 'RS' => 'RSD', 'SE' => 'SEK', 'TR' => 'TRY', 'UA' => 'UAH',
        'AL' => 'ALL', 'BA' => 'BAM', 'MD' => 'MDL', 'MK' => 'MKD',

        // Maghreb et Proche-Orient
        'MA' => 'MAD', 'DZ' => 'DZD', 'TN' => 'TND', 'LY' => 'LYD', 'EG' => 'EGP',
        'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD',
        'OM' => 'OMR', 'JO' => 'JOD', 'LB' => 'LBP', 'IL' => 'ILS',

        // Afrique de l'Ouest et centrale
        'SN' => 'XOF', 'CI' => 'XOF', 'ML' => 'XOF', 'BF' => 'XOF', 'NE' => 'XOF',
        'TG' => 'XOF', 'BJ' => 'XOF', 'GW' => 'XOF',
        'CM' => 'XAF', 'GA' => 'XAF', 'CG' => 'XAF', 'TD' => 'XAF', 'CF' => 'XAF',
        'GQ' => 'XAF',
        'NG' => 'NGN', 'GH' => 'GHS', 'KE' => 'KES', 'ZA' => 'ZAR', 'MU' => 'MUR',

        // Amériques et Asie-Pacifique
        'US' => 'USD', 'CA' => 'CAD', 'MX' => 'MXN', 'BR' => 'BRL', 'AR' => 'ARS',
        'CL' => 'CLP', 'CO' => 'COP', 'PE' => 'PEN',
        'AU' => 'AUD', 'NZ' => 'NZD', 'JP' => 'JPY', 'CN' => 'CNY', 'IN' => 'INR',
        'ID' => 'IDR', 'SG' => 'SGD', 'MY' => 'MYR', 'TH' => 'THB', 'PH' => 'PHP',
        'KR' => 'KRW', 'HK' => 'HKD', 'VN' => 'VND',
    ];

    /**
     * La devise attendue pour ce pays, ou `null` si la table ne le connaît pas.
     *
     * RENDRE `null` PLUTÔT QUE `EUR` EST LE POINT DE CE FICHIER. Un repli muet sur l'euro est
     * exactement le défaut qu'on corrige : il donne une réponse fausse avec l'assurance d'une
     * réponse juste. L'appelant décide quoi faire d'un pays inconnu — proposer, demander, refuser —
     * mais il le fait en sachant qu'il ne sait pas.
     */
    public static function pour(?string $codePays): ?string
    {
        if ($codePays === null) {
            return null;
        }

        $code = strtoupper(trim($codePays));

        return self::TABLE[$code] ?? null;
    }

    /** Ce code ISO 4217 est-il l'un de ceux que nous savons servir ? */
    public static function estUneDeviseConnue(?string $devise): bool
    {
        if ($devise === null) {
            return false;
        }

        return in_array(strtoupper(trim($devise)), self::devisesConnues(), true);
    }

    /**
     * Toutes les devises de la table, triées.
     *
     * @return list<string>
     */
    public static function devisesConnues(): array
    {
        $devises = array_values(array_unique(array_values(self::TABLE)));
        sort($devises);

        return $devises;
    }

    /**
     * Tous les pays connus, code ISO => devise.
     *
     * @return array<string, string>
     */
    public static function table(): array
    {
        return self::TABLE;
    }
}
