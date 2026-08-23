<?php

namespace App\Services\Localization;

use App\Support\International\DeviseParPays;
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
 * Les devises supportées sont celles de `DeviseParPays` — soixante et une — et non une
 * seconde liste tenue ici : voir `devisesSupportees()`.
 */
class Money
{
    public const DEFAULT_CURRENCY = 'EUR';

    /**
     * SYMBOLES — ET SEULEMENT CEUX QUI NE PRÊTENT À AUCUNE CONFUSION.
     *
     * Toute devise absente d'ici s'affiche par son CODE ISO. C'est délibéré : « kr » désigne cinq
     * couronnes différentes et « $ » une quinzaine de dollars. Un code ISO n'est jamais faux ; un
     * symbole ambigu l'est la moitié du temps.
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
     * Formater 1000 yens en « 1 000,00 ¥ » est aussi faux que d'afficher trois décimales à un
     * euro : le yen n'a pas de sous-unité, et le dinar koweïtien en a mille.
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
     * Ce service portait sa PROPRE liste de cinq devises — EUR, USD, GBP, CHF, CAD — pendant que
     * `DeviseParPays` en déclarait soixante et une. Les cinquante-six autres, dont le dirham
     * marocain d'un marché annoncé, ne tombaient pas en erreur : elles étaient RÉÉCRITES EN EUROS.
     * `format(100, 'MAD')` rendait « 100,00 € ».
     *
     * Afficher une devise fausse avec aplomb est pire que ne rien afficher. Une seule table fait
     * donc foi désormais, et une devise qu'elle ignore garde son code au lieu d'en emprunter un
     * autre.
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

        /*
         * UNE DEVISE INCONNUE GARDE SON CODE — elle n'est plus réécrite en euros.
         *
         * Cette ligne valait `$currency = self::DEFAULT_CURRENCY`, et c'est ainsi qu'un montant en
         * dirhams s'affichait « 100,00 € ». Le repli le plus sûr n'est pas la devise par défaut :
         * c'est le code ISO tel quel, qui ne ment sur rien.
         */

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
        return collect(self::devisesSupportees())
            ->map(fn ($info, $code) => [
                'code' => $code,
                'symbol' => $info['symbol'],
                'name' => $info['name'],
            ])
            ->values()
            ->all();
    }

    /**
     * Symbole d'une devise (€, $, £, etc.)
     */
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

    private function fallbackFormat(float $amount, string $currency, string $locale): string
    {
        // Même règle qu'au-dessus : une devise inconnue garde son code et ses deux décimales,
        // elle n'emprunte pas le symbole de l'euro.
        $info = self::devisesSupportees()[$currency] ?? ['symbol' => $currency, 'decimals' => 2];
        $symbol = $info['symbol'];
        $decimals = $info['decimals'];

        // Locales européennes (fr_BE, nl_BE, fr) : virgule décimale + espace milliers
        $isEuropean = str_starts_with($locale, 'fr')
            || str_starts_with($locale, 'nl')
            || str_starts_with($locale, 'de');

        $decimalSep = $isEuropean ? ',' : '.';
        $thousandsSep = $isEuropean ? ' ' : ',';

        $formatted = number_format($amount, $decimals, $decimalSep, $thousandsSep);

        // Position du symbole selon la locale
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
