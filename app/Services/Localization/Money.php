<?php

namespace App\Services\Localization;

use NumberFormatter;

/**
 * Phase 9 — Service de gestion des devises et formatage monétaire.
 *
 * Fonctions :
 *   - format($amount, 'EUR', 'fr')   → "1 234,56 €"
 *   - format($amount, 'USD', 'en')   → "$1,234.56"
 *   - format($amount, 'GBP', 'nl')   → "£ 1.234,56"
 *   - convert($amount, 'EUR', 'USD') → conversion via taux
 *
 * Les taux de change sont stockés dans la table currency_rates créée par la
 * migration Phase 9. Mise à jour manuelle ou via job artisan
 * `php artisan currencies:refresh` (à brancher sur ECB ou autre source).
 *
 * Devises supportées : EUR, USD, GBP, CHF, CAD (extensible via config).
 */
class Money
{
    public const DEFAULT_CURRENCY = 'EUR';

    public const SUPPORTED_CURRENCIES = [
        'EUR' => ['symbol' => '€',  'name' => 'Euro',          'decimals' => 2],
        'USD' => ['symbol' => '$',  'name' => 'US Dollar',     'decimals' => 2],
        'GBP' => ['symbol' => '£',  'name' => 'British Pound', 'decimals' => 2],
        'CHF' => ['symbol' => 'CHF','name' => 'Swiss Franc',   'decimals' => 2],
        'CAD' => ['symbol' => 'C$', 'name' => 'Canadian Dollar', 'decimals' => 2],
    ];

    /**
     * Formate un montant avec sa devise selon la locale.
     *
     * Utilise PHP NumberFormatter (extension intl) si disponible,
     * fallback manuel sinon.
     */
    public function format(float $amount, string $currency = self::DEFAULT_CURRENCY, ?string $locale = null): string
    {
        $locale = $this->normalizeLocale($locale ?? app()->getLocale());
        $currency = strtoupper($currency);

        if (! isset(self::SUPPORTED_CURRENCIES[$currency])) {
            $currency = self::DEFAULT_CURRENCY;
        }

        if (class_exists(NumberFormatter::class) && extension_loaded('intl')) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);
            // intl peut retourner des espaces insécables (\xc2\xa0) — on normalise
            return str_replace("\xc2\xa0", ' ', $formatted);
        }

        return $this->fallbackFormat($amount, $currency, $locale);
    }

    /**
     * Convertit un montant d'une devise vers une autre.
     *
     * Lit les taux depuis la table currency_rates ou cache si dispo.
     */
    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getRate($from, $to);
        if ($rate === null) {
            // Pas de taux → fallback : retourner le montant initial avec un warning
            \Log::warning("Money: pas de taux disponible {$from} → {$to}");
            return $amount;
        }

        return round($amount * $rate, 2);
    }

    /**
     * Récupère le taux from→to.
     * Stratégie :
     *   1. Cherche le taux direct dans currency_rates
     *   2. Sinon, cherche l'inverse (1 / rate inverse)
     *   3. Sinon, passe par EUR comme pivot (from → EUR → to)
     *   4. Sinon null
     */
    public function getRate(string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        // Tentative cache (clé courte pour éviter les collisions)
        $cacheKey = "currency_rate:{$from}:{$to}";
        if (function_exists('cache') && cache()->has($cacheKey)) {
            return (float) cache()->get($cacheKey);
        }

        $rate = $this->lookupRate($from, $to);

        if ($rate === null) {
            $inverse = $this->lookupRate($to, $from);
            if ($inverse !== null && $inverse > 0) {
                $rate = 1 / $inverse;
            }
        }

        if ($rate === null && $from !== 'EUR' && $to !== 'EUR') {
            $fromToEur = $this->getRate($from, 'EUR');
            $eurToDest = $this->getRate('EUR', $to);
            if ($fromToEur !== null && $eurToDest !== null) {
                $rate = $fromToEur * $eurToDest;
            }
        }

        if ($rate !== null && function_exists('cache')) {
            cache()->put($cacheKey, $rate, 3600); // 1h
        }

        return $rate;
    }

    /**
     * Liste des devises supportées pour les selectboxes.
     *
     * @return array<int, array{code:string, symbol:string, name:string}>
     */
    public function supportedList(): array
    {
        return collect(self::SUPPORTED_CURRENCIES)
            ->map(fn ($info, $code) => [
                'code'   => $code,
                'symbol' => $info['symbol'],
                'name'   => $info['name'],
            ])
            ->values()
            ->all();
    }

    /**
     * Symbole d'une devise (€, $, £, etc.)
     */
    public function symbol(string $currency): string
    {
        return self::SUPPORTED_CURRENCIES[strtoupper($currency)]['symbol'] ?? $currency;
    }

    // ──────────────────────────────────────────────────────
    // Privé
    // ──────────────────────────────────────────────────────

    private function lookupRate(string $from, string $to): ?float
    {
        try {
            $rate = \DB::table('currency_rates')
                ->where('base_currency', $from)
                ->where('quote_currency', $to)
                ->orderByDesc('effective_at')
                ->value('rate');

            return $rate !== null ? (float) $rate : null;
        } catch (\Throwable $e) {
            // Table peut ne pas exister yet (migration pas tournée) → null gracieux
            return null;
        }
    }

    private function fallbackFormat(float $amount, string $currency, string $locale): string
    {
        $info = self::SUPPORTED_CURRENCIES[$currency] ?? self::SUPPORTED_CURRENCIES['EUR'];
        $symbol   = $info['symbol'];
        $decimals = $info['decimals'];

        // Locales européennes (fr_BE, nl_BE, fr) : virgule décimale + espace milliers
        $isEuropean = str_starts_with($locale, 'fr')
            || str_starts_with($locale, 'nl')
            || str_starts_with($locale, 'de');

        $decimalSep   = $isEuropean ? ',' : '.';
        $thousandsSep = $isEuropean ? ' ' : ',';

        $formatted = number_format($amount, $decimals, $decimalSep, $thousandsSep);

        // Position du symbole selon la locale
        return match ($locale) {
            'en', 'en_US', 'en_GB' => $symbol . $formatted,
            default                => $formatted . ' ' . $symbol,
        };
    }

    private function normalizeLocale(string $locale): string
    {
        // Belgique : préfère les variantes BE pour le formatage des nombres
        return match ($locale) {
            'fr' => 'fr_BE',
            'nl' => 'nl_BE',
            'en' => 'en_US',
            default => $locale,
        };
    }
}
