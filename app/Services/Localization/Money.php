<?php

namespace App\Services\Localization;

use App\Support\International\DeviseParPays;

/** Phase 9 — Service de gestion des devises et formatage monétaire. */
class Money
{
    public const DEFAULT_CURRENCY = 'EUR';

    /**
     * SYMBOLES — ET SEULEMENT CEUX QUI NE PRÊTENT À AUCUNE CONFUSION.
     *
     * @var array<string, string>
     */
    private const SYMBOLES = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
        'INR' => '₹',
        'KRW' => '₩',
        'ILS' => '₪',
        'VND' => '₫',
        'NGN' => '₦',
        'PHP' => '₱',
        'THB' => '฿',
        'UAH' => '₴',
        'TRY' => '₺',
        'BRL' => 'R$',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'NZD' => 'NZ$',
        'HKD' => 'HK$',
        'SGD' => 'S$',
        'MXN' => 'MX$',
        'PLN' => 'zł',
    ];

    /**
     * DÉCIMALES — les exceptions à la règle des deux, d'après la norme ISO 4217.
     *
     * @var array<string, int>
     */
    private const DECIMALES = [
        'JPY' => 0, 'KRW' => 0, 'VND' => 0, 'CLP' => 0, 'ISK' => 0, 'XAF' => 0, 'XOF' => 0,
        'BHD' => 3, 'JOD' => 3, 'KWD' => 3, 'OMR' => 3, 'TND' => 3, 'LYD' => 3,
    ];

    /**
     * LA LISTE DES DEVISES VIENT DE `DeviseParPays`, ET DE NULLE PART AILLEURS.
     *
     * @return array<string, array{symbol: string, name: string, decimals: int}>
     */
    public static function devisesSupportees(): array
    {
        static $table = null;

        if ($table !== null) {
            return $table;
        }

        $table = [];

        foreach (DeviseParPays::devisesConnues() as $code) {
            $table[$code] = [
                'symbol' => self::SYMBOLES[$code] ?? $code,
                'name' => $code,
                'decimals' => self::DECIMALES[$code] ?? 2,
            ];
        }

        return $table;
    }

    /** Formate un montant avec sa devise selon la locale. */
    public function format(float $amount, string $currency = self::DEFAULT_CURRENCY, ?string $locale = null): string
    {
        $locale = $this->normalizeLocale($locale ?? app()->getLocale());
        $currency = strtoupper($currency);

        // UNE DEVISE INCONNUE GARDE SON CODE — elle n'est plus réécrite en euros.
        // Le rendu ne passe JAMAIS par ICU : sa sortie change avec la version installée.
        return $this->formatDeterministe($amount, $currency, $locale);
    }

    /** Convertit un montant d'une devise vers une autre. */
    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

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

    /** Récupère le taux from→to. Stratégie : 1. Cherche le taux direct dans currency_rates 2. */
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
        return collect(self::devisesSupportees())
            ->map(fn ($info, $code) => [
                'code' => $code,
                'symbol' => $info['symbol'],
                'name' => $info['name'],
            ])
            ->values()
            ->all();
    }

    /** Symbole d'une devise (€, $, £, etc.) */
    public function symbol(string $currency): string
    {
        return self::devisesSupportees()[strtoupper($currency)]['symbol'] ?? strtoupper($currency);
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

    /**
     * LE SEUL rendu monétaire de la plateforme : un montant s'affiche pareil sur toute machine.
     * Une devise inconnue garde son code et deux décimales, jamais le symbole de l'euro.
     */
    private function formatDeterministe(float $amount, string $currency, string $locale): string
    {
        $info = self::devisesSupportees()[$currency] ?? ['symbol' => $currency, 'decimals' => 2];
        $symbol = $info['symbol'];
        $decimals = $info['decimals'];

        $estEuropeen = str_starts_with($locale, 'fr')
            || str_starts_with($locale, 'nl')
            || str_starts_with($locale, 'de');

        $separateurDecimal = $estEuropeen ? ',' : '.';

        // L'allemand groupe par le POINT là où le français emploie l'espace.
        $separateurMilliers = match (true) {
            str_starts_with($locale, 'de') => '.',
            $estEuropeen => ' ',
            default => ',',
        };

        $formatted = number_format($amount, $decimals, $separateurDecimal, $separateurMilliers);

        // L'anglais préfixe le symbole, les autres le suffixent.
        return match ($locale) {
            'en', 'en_US', 'en_GB' => $symbol.$formatted,
            default => $formatted.' '.$symbol,
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
