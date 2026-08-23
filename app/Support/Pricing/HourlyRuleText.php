<?php

namespace App\Support\Pricing;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;

/** LA RÈGLE DU TEMPS, RENDUE — avec ses chiffres, sans que personne ait à les fournir. */
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
            // LES NOMBRES BRUTS ACCOMPAGNENT LES PHRASES.
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

    /** « 1,30 » en français et en néerlandais, « 1.30 » en anglais. */
    private static function multiplicateurLisible(string $locale): string
    {
        $valeur = self::multiplicateur();

        // `extension_loaded('intl')` ET NON `class_exists(NumberFormatter::class)`.
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
