<?php

return [
    'enabled' => env('CANCELLATION_V2_ENABLED', true),

    'default_currency' => env('CANCELLATION_DEFAULT_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Refund provider — stripe | wallet | mock
    |--------------------------------------------------------------------------
    | stripe : appelle Stripe refunds API via Cashier / Stripe SDK
    | wallet : crédite le wallet provider du client
    | mock   : log-only (dev/tests)
    */
    'default_refund_method' => env('CANCELLATION_REFUND_METHOD', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Notifications cross-flow
    |--------------------------------------------------------------------------
    */
    'notify_provider_on_client_cancel' => env('CANCELLATION_NOTIFY_PROVIDER', true),
    'notify_client_on_provider_cancel' => env('CANCELLATION_NOTIFY_CLIENT', true),

    /*
    |--------------------------------------------------------------------------
    | Integrations toggles (peuvent être désactivées modules-par-modules)
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        'stripe_refund' => env('CANCELLATION_INTEG_STRIPE', true),
        'loyalty_forfeit' => env('CANCELLATION_INTEG_LOYALTY', true),
        'promo_restore' => env('CANCELLATION_INTEG_PROMO', true),
        'insurance_cancel' => env('CANCELLATION_INTEG_INSURANCE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Actor roles supportés (whitelistés en input API)
    |--------------------------------------------------------------------------
    */
    'actor_roles' => ['client', 'provider', 'admin'],

    /*
    |--------------------------------------------------------------------------
    | Statut booking après cancellation (selon actor)
    |--------------------------------------------------------------------------
    */
    'booking_status_after_cancel' => [
        'client' => 'annule',
        'provider' => 'annule',
        'admin' => 'annule',
    ],
];
