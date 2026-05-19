<?php

namespace App\Services\Fx\Providers;

use App\Services\Fx\FxProviderInterface;
use App\Services\Fx\FxRate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Open Exchange Rates — multi-base via /api/latest.json?base=XXX (free tier = USD only).
 */
class OpenExchangeRatesFxProvider implements FxProviderInterface
{
    public function name(): string
    {
        return 'openexchange';
    }

    public function supportedBases(): array
    {
        // Free tier supports only USD as base. Paid tiers support multiple.
        return ['USD'];
    }

    public function fetchRates(string $base, array $quotes): array
    {
        $appId = (string) Config::get('fx.providers.openexchange.app_id', '');
        $baseUrl = rtrim((string) Config::get('fx.providers.openexchange.base_url', 'https://openexchangerates.org/api'), '/');
        $timeout = (int) Config::get('fx.providers.openexchange.http_timeout', 10);

        if (! $appId) {
            return [];
        }

        try {
            $response = Http::timeout($timeout)->get("{$baseUrl}/latest.json", [
                'app_id' => $appId,
                'base' => strtoupper($base),
                'symbols' => implode(',', array_map('strtoupper', $quotes)),
            ]);

            if (! $response->ok()) {
                Log::warning('OpenExchangeRates: HTTP error', ['status' => $response->status()]);
                return [];
            }

            $data = $response->json();
            $rates = (array) ($data['rates'] ?? []);
            $base = strtoupper((string) ($data['base'] ?? $base));

            $out = [];
            foreach ($rates as $code => $rate) {
                $code = strtoupper((string) $code);
                $rate = (float) $rate;
                if ($rate > 0) {
                    $out[] = new FxRate($base, $code, $rate, $this->name(), now(), ['raw' => $rate]);
                }
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('OpenExchangeRates: exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
