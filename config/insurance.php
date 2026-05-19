<?php

return [
    'enabled' => env('INSURANCE_ENABLED', true),

    'default_provider' => env('INSURANCE_PROVIDER', 'mock'),
    'default_currency' => env('INSURANCE_DEFAULT_CURRENCY', 'EUR'),

    'providers' => [
        'mock' => [
            'driver' => 'mock',
        ],
        'hiscox' => [
            'driver' => 'hiscox',
            'api_key' => env('HISCOX_API_KEY'),
            'partner_id' => env('HISCOX_PARTNER_ID'),
            'webhook_secret' => env('HISCOX_WEBHOOK_SECRET'),
            'base_url' => env('HISCOX_BASE_URL', 'https://api.hiscox.com/v1'),
        ],
        'wakam' => [
            'driver' => 'wakam',
            'api_key' => env('WAKAM_API_KEY'),
            'webhook_secret' => env('WAKAM_WEBHOOK_SECRET'),
            'base_url' => env('WAKAM_BASE_URL', 'https://api.wakam.com/v1'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Claims rules
    |--------------------------------------------------------------------------
    | filing_window_days : fenêtre pour filer un claim après l'incident
    | max_factor : claim_amount maximum = N × premium_cents
    */
    'claims' => [
        'filing_window_days' => (int) env('INSURANCE_CLAIM_WINDOW_DAYS', 30),
        'max_amount_factor' => (int) env('INSURANCE_CLAIM_MAX_FACTOR', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-cancel policies on booking cancel
    |--------------------------------------------------------------------------
    */
    'cancel_with_booking' => env('INSURANCE_CANCEL_WITH_BOOKING', true),
];
