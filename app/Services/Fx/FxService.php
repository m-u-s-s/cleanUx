<?php

namespace App\Services\Fx;

use App\Models\Currency;
use App\Models\CurrencyConversion;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\Fx\Providers\EcbFxProvider;
use App\Services\Fx\Providers\FxMockProvider;
use App\Services\Fx\Providers\OpenExchangeRatesFxProvider;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FxService — orchestre fetch + persist + cache + convert.
 *
 * Workflow getRate(base, quote) :
 *   1. Pair identique (base == quote) → 1.0 immédiat
 *   2. Lookup cache (TTL minutes)
 *   3. Lookup DB le plus récent (si fresh selon cache_ttl_minutes)
 *   4. Sinon → refreshRates() → essaie primary provider, puis fallback chain
 *   5. Si tout échoue → ExchangeRate::SOURCE_FALLBACK 1:1 + log warning
 *
 * Convert: rate × amount × (1 + fee_percent/100). Loggué dans currency_conversions.
 */
class FxService
{
    public function __construct(protected FxProviderInterface $defaultProvider)
    {
    }

    /**
     * Resolve the current rate (most recent ExchangeRate) for the pair.
     */
    public function getRate(string $base, string $quote): ?ExchangeRate
    {
        $base = strtoupper($base);
        $quote = strtoupper($quote);

        if ($base === $quote) {
            // Identity rate, ephemeral (not persisted)
            return new ExchangeRate([
                'base_currency' => $base,
                'quote_currency' => $quote,
                'rate' => '1.00000000',
                'source' => ExchangeRate::SOURCE_FALLBACK,
                'fetched_at' => now(),
                'valid_from' => now(),
            ]);
        }

        $ttl = (int) Config::get('fx.cache_ttl_minutes', 15);

        $cacheKey = "fx:rate:{$base}:{$quote}";

        return Cache::remember($cacheKey, now()->addMinutes($ttl), function () use ($base, $quote, $ttl) {
            $existing = ExchangeRate::query()
                ->pair($base, $quote)
                ->fresh($ttl)
                ->orderByDesc('fetched_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            // Need to fetch — try primary then fallback chain
            $rate = $this->fetchAndPersist($base, [$quote]);
            return $rate;
        });
    }

    /**
     * @param array<int,string> $quotes
     */
    public function fetchAndPersist(string $base, array $quotes): ?ExchangeRate
    {
        $base = strtoupper($base);
        $quotes = array_map('strtoupper', $quotes);

        $providers = $this->buildProviderChain();

        foreach ($providers as $provider) {
            $supported = $provider->supportedBases();
            $effectiveBase = in_array($base, $supported, true) ? $base : ($supported[0] ?? 'EUR');

            try {
                $rates = $provider->fetchRates($effectiveBase, $quotes);
                if (empty($rates)) {
                    continue;
                }

                $first = null;
                foreach ($rates as $fx) {
                    // If we had to use a different base (e.g. ECB requires EUR), we'll convert
                    // via cross-rate at lookup time. For simplicity here, we persist with the
                    // effective base.
                    $row = ExchangeRate::create([
                        'base_currency' => $fx->base,
                        'quote_currency' => $fx->quote,
                        'rate' => number_format($fx->rate, 8, '.', ''),
                        'source' => $provider->name(),
                        'fetched_at' => $fx->fetchedAt,
                        'valid_from' => $fx->fetchedAt,
                        'metadata' => $fx->raw,
                    ]);

                    if ($base === $fx->base && in_array($fx->quote, $quotes, true)) {
                        $first ??= $row;
                    }
                }

                if ($first) {
                    return $first;
                }
            } catch (\Throwable $e) {
                Log::warning('FxService: provider exception', [
                    'provider' => $provider->name(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Ultimate fallback: 1:1 rate to avoid blocking the flow
        Log::warning('FxService: all providers failed, using 1:1 fallback', [
            'base' => $base, 'quotes' => $quotes,
        ]);

        if (! empty($quotes)) {
            $quote = $quotes[0];
            return ExchangeRate::create([
                'base_currency' => $base,
                'quote_currency' => $quote,
                'rate' => '1.00000000',
                'source' => ExchangeRate::SOURCE_FALLBACK,
                'fetched_at' => now(),
                'valid_from' => now(),
                'metadata' => ['warning' => 'all_providers_failed'],
            ]);
        }

        return null;
    }

    /**
     * Force-refresh rates for all active currencies vs base.
     */
    public function refreshAll(?string $base = null): int
    {
        $base = strtoupper($base ?? (string) Config::get('fx.base_currency', 'EUR'));

        $quotes = Currency::query()->active()->pluck('code')->all();
        $quotes = array_values(array_filter($quotes, fn ($c) => $c !== $base));

        if (empty($quotes)) {
            return 0;
        }

        $countBefore = ExchangeRate::count();
        $this->fetchAndPersist($base, $quotes);
        $countAfter = ExchangeRate::count();

        // Invalidate cache for impacted pairs
        foreach ($quotes as $quote) {
            Cache::forget("fx:rate:{$base}:{$quote}");
        }

        ActivityLogger::log('fx.refresh_all', null, [
            'base' => $base,
            'quotes_requested' => count($quotes),
            'rows_inserted' => $countAfter - $countBefore,
        ]);

        return $countAfter - $countBefore;
    }

    public function convert(
        int $amountCents,
        string $sourceCurrency,
        string $targetCurrency,
        ?User $user = null,
        ?Model $source = null,
        ?string $idempotencyKey = null,
    ): CurrencyConversion {
        $sourceCurrency = strtoupper($sourceCurrency);
        $targetCurrency = strtoupper($targetCurrency);

        if ($idempotencyKey) {
            $existing = CurrencyConversion::query()
                ->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $rate = $this->getRate($sourceCurrency, $targetCurrency);
        $rateValue = $rate ? (float) $rate->rate : 1.0;
        $feePercent = (float) Config::get('fx.fee_percent', 0);

        $convertedFloat = $amountCents * $rateValue;
        $afterFee = $convertedFloat * (1 + ($feePercent / 100));
        $targetCents = (int) round($afterFee);

        return DB::transaction(function () use ($amountCents, $sourceCurrency, $targetCurrency, $rate, $rateValue, $feePercent, $targetCents, $user, $source, $idempotencyKey) {
            $row = CurrencyConversion::create([
                'source_amount_cents' => $amountCents,
                'source_currency' => $sourceCurrency,
                'target_amount_cents' => $targetCents,
                'target_currency' => $targetCurrency,
                'exchange_rate_id' => $rate && $rate->exists ? $rate->id : null,
                'rate_used' => number_format($rateValue, 8, '.', ''),
                'fee_percent' => number_format($feePercent, 4, '.', ''),
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'user_id' => $user?->id,
                'idempotency_key' => $idempotencyKey,
                'converted_at' => now(),
                'metadata' => [
                    'rate_source' => $rate?->source ?? 'unknown',
                ],
            ]);

            ActivityLogger::log('fx.converted', $row, [
                'source_currency' => $sourceCurrency,
                'target_currency' => $targetCurrency,
                'source_amount_cents' => $amountCents,
                'target_amount_cents' => $targetCents,
            ]);

            return $row;
        });
    }

    public function provider(): FxProviderInterface
    {
        return $this->defaultProvider;
    }

    /**
     * @return array<int, FxProviderInterface>
     */
    protected function buildProviderChain(): array
    {
        $chain = [$this->defaultProvider];
        $fallback = (array) Config::get('fx.fallback_chain', []);

        $alreadyAddedNames = [$this->defaultProvider->name()];

        foreach ($fallback as $name) {
            if (in_array($name, $alreadyAddedNames, true)) {
                continue;
            }
            $instance = match ($name) {
                'mock' => new FxMockProvider(),
                'ecb' => new EcbFxProvider(),
                'openexchange' => new OpenExchangeRatesFxProvider(),
                default => null,
            };
            if ($instance) {
                $chain[] = $instance;
                $alreadyAddedNames[] = $name;
            }
        }

        return $chain;
    }
}
