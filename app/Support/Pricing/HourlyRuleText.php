<?php

namespace App\Support\Pricing;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;

/**
 * LA RÈGLE DU TEMPS, RENDUE — avec ses chiffres, sans que personne ait à les fournir.
 *
 * POURQUOI CETTE CLASSE PLUTÔT QU'UN `__()` DIRECT. Les phrases de `pricing.php` (fr, nl, en) portent
 * `:multiplier` et `:grace`, qui viennent de `config/order_engine.php`. Un appelant qui oublie de
 * les passer n'obtient pas une erreur : il obtient la phrase avec « :grace minutes de tolérance »
 * affiché tel quel au client. Sept surfaces devaient répéter le même tableau de paramètres ; il
 * suffisait qu'une seule l'oublie.
 *
 * LE MULTIPLICATEUR EST FORMATÉ SELON LA LOCALE. « 1.3 » en français se lit mal et « 1,3 » en
 * néerlandais aussi — chaque langue a sa virgule décimale, et un tarif mal ponctué fait douter du
 * reste du contrat.
 */
class HourlyRuleText
{
    /** L'annonce courte — sous un sélecteur, dans un bandeau, sur une carte. */
    public static function courte(?string $locale = null): string
    {
        return self::rendre('pricing.hourly.rule_short', $locale);
    }

    /** La version contractuelle : franchise, plafond, empilement des majorations. */
    public static function complete(?string $locale = null): string
    {
        return self::rendre('pricing.hourly.rule_full', $locale);
    }

    /** La même règle, vue du prestataire : ce qu'il touche, et ce qu'il doit dire au client. */
    public static function prestataire(?string $locale = null): string
    {
        return self::rendre('pricing.hourly.rule_provider', $locale);
    }

    /**
     * Les trois formulations d'un coup — ce que l'API sert au mobile.
     *
     * @return array{short: string, full: string, provider: string, multiplier: float, grace_minutes: int, cap_ratio: float}
     */
    public static function contrat(?string $locale = null): array
    {
        return [
            'short' => self::courte($locale),
            'full' => self::complete($locale),
            'provider' => self::prestataire($locale),
            /*
             * LES NOMBRES BRUTS ACCOMPAGNENT LES PHRASES.
             *
             * Une application peut vouloir afficher « ×1,30 » dans un badge sans reconstruire une
             * phrase entière. Les lui faire extraire du texte par une expression régulière
             * garantirait qu'elle se trompe le jour où la phrase change.
             */
            'multiplier' => self::multiplicateur(),
            'grace_minutes' => self::franchise(),
            'cap_ratio' => (float) Config::get('order_engine.overtime_cap_ratio', 1.0),
        ];
    }

    public static function multiplicateur(): float
    {
        return (float) Config::get('order_engine.overtime_multiplier', 1.30);
    }

    public static function franchise(): int
    {
        return max(0, (int) Config::get('order_engine.overtime_grace_minutes', 15));
    }

    private static function rendre(string $cle, ?string $locale): string
    {
        $locale ??= app()->getLocale();

        return (string) Lang::get($cle, [
            'multiplier' => self::multiplicateurLisible($locale),
            'grace' => (string) self::franchise(),
        ], $locale);
    }

    /**
     * « 1,30 » en français et en néerlandais, « 1.30 » en anglais.
     *
     * On ne passe pas par `number_format()` avec un séparateur codé en dur : la locale sert
     * précisément à ça, et la liste des langues du projet grandira.
     */
    private static function multiplicateurLisible(string $locale): string
    {
        $valeur = self::multiplicateur();

        /*
         * `extension_loaded('intl')` ET NON `class_exists(NumberFormatter::class)`.
         *
         * Symfony embarque un polyfill qui DÉFINIT la classe sans l'implémenter : `class_exists`
         * répond oui, puis le constructeur lève une exception pour toute locale autre que
         * l'anglais. Le garde-fou laissait donc passer, et la page d'un client francophone tombait
         * en erreur 500 — sur une machine où `intl` est installé, on ne le voit jamais.
         */
        if (extension_loaded('intl')) {
            $formateur = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formateur->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
            $formateur->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

            $rendu = $formateur->format($valeur);

            if ($rendu !== false) {
                return $rendu;
            }
        }

        // Repli sans l'extension intl : la virgule décimale des trois langues du projet.
        return number_format($valeur, 2, str_starts_with($locale, 'en') ? '.' : ',', '');
    }
}
