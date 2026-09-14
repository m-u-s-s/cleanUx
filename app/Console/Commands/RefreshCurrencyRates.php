<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RefreshCurrencyRates extends Command
{
    protected $signature = 'currencies:refresh';

    protected $description = 'Refresh currency rates from ECB';

    public function handle(): int
    {
        // ECB API gratuite, pas de clé requise
        $response = Http::get('https://api.frankfurter.app/latest', [
            'from' => 'EUR',
            'to' => 'USD,GBP,CHF,CAD',
        ]);

        if (! $response->ok()) {
            $this->error('Échec récupération taux ECB');

            return self::FAILURE;
        }

        $rates = $response->json('rates', []);
        $now = now();
        $oublies = [];

        foreach ($rates as $quote => $rate) {
            \DB::table('currency_rates')->insert([
                'base_currency' => 'EUR',
                'quote_currency' => $quote,
                'rate' => $rate,
                'effective_at' => $now,
                'source' => 'frankfurter.app',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $oublies[] = "fx:rate:EUR:{$quote}";
            $oublies[] = "currency_rate:EUR:{$quote}";
            $this->info("EUR → {$quote} = {$rate}");
        }

        /*
         * ON N'OUBLIE QUE LES TAUX QU'ON VIENT DE RÉÉCRIRE.
         *
         * `cache()->flush()` était un FLUSHDB sur le magasin par défaut, tous les matins à 06:00 :
         * il emportait les 46 mutex `withoutOverlapping()` de l'ordonnanceur, le verrou
         * `ShouldBeUnique` de l'affectation automatique et TOUS les compteurs de `RateLimiter`
         * — dont le throttle de connexion. Deux clés par devise suffisent à forcer la relecture.
         */
        foreach ($oublies as $cle) {
            Cache::forget($cle);
        }

        return self::SUCCESS;
    }
}
