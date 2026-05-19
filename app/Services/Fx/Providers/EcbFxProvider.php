<?php

namespace App\Services\Fx\Providers;

use App\Services\Fx\FxProviderInterface;
use App\Services\Fx\FxRate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * European Central Bank — flux XML public, base EUR uniquement.
 *
 * https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml
 *
 * Pour passer d'une autre base, le service caller doit cross-rate via EUR.
 */
class EcbFxProvider implements FxProviderInterface
{
    public function name(): string
    {
        return 'ecb';
    }

    public function supportedBases(): array
    {
        return ['EUR'];
    }

    public function fetchRates(string $base, array $quotes): array
    {
        $base = strtoupper($base);
        if ($base !== 'EUR') {
            // ECB ne fournit que des taux depuis EUR
            return [];
        }

        $url = (string) Config::get('fx.providers.ecb.feed_url', 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
        $timeout = (int) Config::get('fx.providers.ecb.http_timeout', 10);

        try {
            $response = Http::timeout($timeout)->get($url);
            if (! $response->ok()) {
                Log::warning('EcbFxProvider: HTTP error', ['status' => $response->status()]);
                return [];
            }

            $xml = @simplexml_load_string($response->body());
            if (! $xml) {
                Log::warning('EcbFxProvider: failed to parse XML');
                return [];
            }

            $ratesByCode = [];
            // ECB XML structure: Envelope > Cube > Cube[time] > Cube[currency,rate]
            foreach ($xml->Cube->Cube->Cube ?? [] as $node) {
                $code = (string) $node['currency'];
                $rate = (float) $node['rate'];
                if ($code && $rate > 0) {
                    $ratesByCode[$code] = $rate;
                }
            }

            $out = [];
            foreach ($quotes as $quote) {
                $quote = strtoupper($quote);
                if ($quote === 'EUR') {
                    $out[] = new FxRate('EUR', 'EUR', 1.0, $this->name(), now());
                    continue;
                }
                if (! isset($ratesByCode[$quote])) {
                    continue;
                }
                $out[] = new FxRate('EUR', $quote, $ratesByCode[$quote], $this->name(), now());
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('EcbFxProvider: exception', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
