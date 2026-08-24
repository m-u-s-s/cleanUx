<?php

use App\Services\I18n\LocaleFormatter;
use App\View\Components\Money;

if (! function_exists('locale_date')) {
    function locale_date($value, ?string $locale = null, string $style = 'medium'): string
    {
        return app(LocaleFormatter::class)->date($value, $locale, $style);
    }
}

if (! function_exists('locale_datetime')) {
    function locale_datetime($value, ?string $locale = null, string $style = 'medium'): string
    {
        return app(LocaleFormatter::class)->dateTime($value, $locale, $style);
    }
}

if (! function_exists('locale_currency')) {
    /**
     * Formate un montant. Sans devise explicite, prend CELLE DU CONTEXTE — pas l'euro.
     * A employer la ou un composant Blade ne passe pas : attribut, expression PHP.
     */
    function locale_currency($amount, ?string $currency = null, ?string $locale = null): string
    {
        $currency = $currency ?: Money::deviseDuContexte();

        return app(LocaleFormatter::class)->currency($amount, $currency, $locale);
    }
}

if (! function_exists('locale_number')) {
    function locale_number($value, ?string $locale = null, int $decimals = 2): string
    {
        return app(LocaleFormatter::class)->number($value, $locale, $decimals);
    }
}
