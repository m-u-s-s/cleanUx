<?php

namespace App\Services\Fx\Providers;

use App\Services\Fx\FxProviderInterface;
use App\Services\Fx\FxRate;

/**
 * Provider FX mock — taux figés pour dev/tests, déterministe.
 *
 * Comportement spécial : quote === "FAIL" → renvoie un tableau vide (simule provider down).
 */
class FxMockProvider implements FxProviderInterface
{
    protected const RATES_FROM_EUR = [
        'USD' => 1.0850,
        'GBP' => 0.8550,
        'CHF' => 0.9620,
        'CAD' => 1.4700,
        'AUD' => 1.6200,
        'JPY' => 165.50,
        'NOK' => 11.40,
        'SEK' => 11.10,
        'DKK' => 7.46,
        'PLN' => 4.30,
        'CZK' => 25.30,
        'HUF' => 405.00,
    ];

    public function name(): string
    {
        return 'mock';
    }

    public function supportedBases(): array
    {
        return ['EUR'];
    }

    public function fetchRates(string $base, array $quotes): array
    {
        if (in_array('FAIL', $quotes, true)) {
            return [];
        }

        $base = strtoupper($base);
        $rates = [];

        // We define rates from EUR. To translate from another base, invert / cross.
        foreach ($quotes as $quote) {
            $quote = strtoupper($quote);
            if ($quote === $base) {
                $rates[] = new FxRate($base, $quote, 1.0, $this->name(), now(), ['identity' => true]);
                continue;
            }

            $rate = $this->computeCrossRate($base, $quote);
            if ($rate === null) {
                continue;
            }

            $rates[] = new FxRate($base, $quote, $rate, $this->name(), now(), ['cross_via_eur' => $base !== 'EUR']);
        }

        return $rates;
    }

    protected function computeCrossRate(string $base, string $quote): ?float
    {
        if ($base === 'EUR') {
            return self::RATES_FROM_EUR[$quote] ?? null;
        }
        if ($quote === 'EUR') {
            return isset(self::RATES_FROM_EUR[$base])
                ? round(1.0 / self::RATES_FROM_EUR[$base], 8)
                : null;
        }
        // Cross-rate via EUR : base→EUR→quote
        $eurFromBase = self::RATES_FROM_EUR[$base] ?? null;
        $quoteFromEur = self::RATES_FROM_EUR[$quote] ?? null;
        if ($eurFromBase === null || $quoteFromEur === null) {
            return null;
        }
        return round($quoteFromEur / $eurFromBase, 8);
    }
}
