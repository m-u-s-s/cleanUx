<?php

return [
    'enabled' => env('FX_ENABLED', true),

    'base_currency' => env('FX_BASE_CURRENCY', 'EUR'),

    'default_provider' => env('FX_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'driver' => 'mock',
        ],
        'ecb' => [
            'driver' => 'ecb',
            'feed_url' => env('FX_ECB_FEED_URL', 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml'),
            'http_timeout' => (int) env('FX_HTTP_TIMEOUT', 10),
        ],
        'openexchange' => [
            'driver' => 'openexchange',
            'app_id' => env('FX_OPENEXCHANGE_APP_ID'),
            'base_url' => env('FX_OPENEXCHANGE_BASE_URL', 'https://openexchangerates.org/api'),
            'http_timeout' => (int) env('FX_HTTP_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback chain — si default_provider échoue, on tente les suivants
    | dans l'ordre. Dernière chaîne = "fallback" (taux 1:1 + warning) si rien
    | n'est disponible (évite de bloquer le checkout).
    |--------------------------------------------------------------------------
    */
    'fallback_chain' => ['ecb', 'mock'],

    /*
    |--------------------------------------------------------------------------
    | Cache & fraîcheur
    |--------------------------------------------------------------------------
    | cache_ttl_minutes : durée pendant laquelle un rate fetch est servi du cache
    | rate_stale_warning_hours : au-delà, un warning est loggé / un badge dans l'admin
    */
    'cache_ttl_minutes' => (int) env('FX_CACHE_TTL_MIN', 15),
    'rate_stale_warning_hours' => (int) env('FX_STALE_WARNING_HOURS', 24),
    'rate_stale_block_hours' => (int) env('FX_STALE_BLOCK_HOURS', 168),

    /*
    |--------------------------------------------------------------------------
    | Frais de conversion (margin applied on top of provider rate)
    |--------------------------------------------------------------------------
    */
    'fee_percent' => (float) env('FX_FEE_PERCENT', 0.0),
];
