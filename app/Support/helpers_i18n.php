<?php

use App\Services\I18n\LocaleFormatter;

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
    function locale_currency($amount, ?string $currency = null, ?string $locale = null): string
    {
        return app(LocaleFormatter::class)->currency($amount, $currency, $locale);
    }
}

if (! function_exists('locale_number')) {
    function locale_number($value, ?string $locale = null, int $decimals = 2): string
    {
        return app(LocaleFormatter::class)->number($value, $locale, $decimals);
    }
}
