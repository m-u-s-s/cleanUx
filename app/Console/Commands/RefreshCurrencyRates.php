<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
            'to'   => 'USD,GBP,CHF,CAD',
        ]);

        if (! $response->ok()) {
            $this->error('Échec récupération taux ECB');
            return self::FAILURE;
        }

        $rates = $response->json('rates', []);
        $now = now();

        foreach ($rates as $quote => $rate) {
            \DB::table('currency_rates')->insert([
                'base_currency'  => 'EUR',
                'quote_currency' => $quote,
                'rate'           => $rate,
                'effective_at'   => $now,
                'source'         => 'frankfurter.app',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $this->info("EUR → {$quote} = {$rate}");
        }

        // Vide le cache pour forcer la relecture
        cache()->flush();

        return self::SUCCESS;
    }
}