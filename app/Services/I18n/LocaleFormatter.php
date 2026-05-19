<?php

namespace App\Services\I18n;

use Carbon\CarbonInterface;
use DateTimeInterface;
use IntlDateFormatter;
use NumberFormatter;

/**
 * Formatage de dates / nombres / devises selon la locale active.
 *
 * Utilise l'extension intl si disponible (Linux/Docker), fallback gracieux
 * sur des formats manuels par locale sinon.
 */
class LocaleFormatter
{
    public function __construct(protected LocaleResolver $resolver)
    {
    }

    public function date(DateTimeInterface|string|null $value, ?string $locale = null, string $style = 'medium'): string
    {
        if ($value === null) {
            return '';
        }

        $locale = $locale ?? app()->getLocale();
        $bcp47 = $this->resolver->bcp47($locale);
        $dt = $this->toDateTime($value);

        if (extension_loaded('intl')) {
            $formatter = new IntlDateFormatter(
                $bcp47,
                $this->intlStyle($style),
                IntlDateFormatter::NONE,
            );
            $formatted = $formatter->format($dt);
            if ($formatted !== false) {
                return (string) $formatted;
            }
        }

        return $this->fallbackDate($dt, $locale, $style);
    }

    public function dateTime(DateTimeInterface|string|null $value, ?string $locale = null, string $style = 'medium'): string
    {
        if ($value === null) {
            return '';
        }

        $locale = $locale ?? app()->getLocale();
        $bcp47 = $this->resolver->bcp47($locale);
        $dt = $this->toDateTime($value);

        if (extension_loaded('intl')) {
            $formatter = new IntlDateFormatter(
                $bcp47,
                $this->intlStyle($style),
                IntlDateFormatter::SHORT,
            );
            $formatted = $formatter->format($dt);
            if ($formatted !== false) {
                return (string) $formatted;
            }
        }

        return $this->fallbackDate($dt, $locale, $style) . ' ' . $dt->format('H:i');
    }

    public function currency(float|int|string|null $amount, ?string $currency = null, ?string $locale = null): string
    {
        if ($amount === null) {
            return '';
        }

        $locale = $locale ?? app()->getLocale();
        $bcp47 = $this->resolver->bcp47($locale);
        $currency = $currency ?? config("i18n.locales.{$locale}.currency", 'EUR');

        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($bcp47, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency((float) $amount, $currency);
            if ($formatted !== false) {
                return (string) $formatted;
            }
        }

        return $this->fallbackCurrency((float) $amount, $currency, $locale);
    }

    public function number(float|int|string|null $value, ?string $locale = null, int $decimals = 2): string
    {
        if ($value === null) {
            return '';
        }

        $locale = $locale ?? app()->getLocale();
        $bcp47 = $this->resolver->bcp47($locale);

        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter($bcp47, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatted = $formatter->format((float) $value);
            if ($formatted !== false) {
                return (string) $formatted;
            }
        }

        return $this->fallbackNumber((float) $value, $locale, $decimals);
    }

    protected function toDateTime(DateTimeInterface|string $value): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }
        if ($value instanceof CarbonInterface) {
            return $value->toDateTime();
        }
        return new \DateTime((string) $value);
    }

    protected function intlStyle(string $style): int
    {
        return match ($style) {
            'short' => IntlDateFormatter::SHORT,
            'long' => IntlDateFormatter::LONG,
            'full' => IntlDateFormatter::FULL,
            default => IntlDateFormatter::MEDIUM,
        };
    }

    protected function fallbackDate(DateTimeInterface $dt, string $locale, string $style): string
    {
        $format = match ($locale) {
            'en' => $style === 'short' ? 'm/d/Y' : 'M j, Y',
            'de' => 'd.m.Y',
            'it', 'es', 'pt' => 'd/m/Y',
            'nl' => 'd-m-Y',
            default => 'd/m/Y',
        };
        return $dt->format($format);
    }

    protected function fallbackCurrency(float $amount, string $currency, string $locale): string
    {
        $formatted = $this->fallbackNumber($amount, $locale, 2);

        return match ($locale) {
            'en' => ($currency === 'EUR' ? '€' : $currency) . $formatted,
            default => $formatted . ' ' . ($currency === 'EUR' ? '€' : $currency),
        };
    }

    protected function fallbackNumber(float $value, string $locale, int $decimals): string
    {
        $decimalSep = match ($locale) {
            'en' => '.',
            default => ',',
        };
        $thousandSep = match ($locale) {
            'en' => ',',
            'de' => '.',
            default => ' ',
        };

        return number_format($value, $decimals, $decimalSep, $thousandSep);
    }
}
