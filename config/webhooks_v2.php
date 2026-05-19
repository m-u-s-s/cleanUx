<?php

return [
    'enabled' => env('WEBHOOKS_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | HTTP client defaults
    |--------------------------------------------------------------------------
    */
    'timeout_seconds' => (int) env('WEBHOOKS_TIMEOUT_SECONDS', 15),
    'connect_timeout_seconds' => (int) env('WEBHOOKS_CONNECT_TIMEOUT_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Retry policy (exponential backoff)
    |--------------------------------------------------------------------------
    | attempts schedule (seconds) : 0 (immediate), 30, 120, 600, 1800, 7200
    | After max_attempts → status=dead.
    */
    'max_attempts' => (int) env('WEBHOOKS_MAX_ATTEMPTS', 6),
    'backoff_schedule_seconds' => [30, 120, 600, 1800, 7200, 21600],

    /*
    |--------------------------------------------------------------------------
    | Signature
    |--------------------------------------------------------------------------
    | algo : sha256 (HMAC). header sent : "X-CleanUx-Signature".
    | Format : t=<timestamp>,v1=<hex_hmac>
    */
    'signature_header' => env('WEBHOOKS_SIGNATURE_HEADER', 'X-CleanUx-Signature'),
    'signature_version' => 'v1',
    'signature_algo' => 'sha256',
    'signature_tolerance_seconds' => (int) env('WEBHOOKS_SIG_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Auto-suspend endpoint after N consecutive failures
    |--------------------------------------------------------------------------
    */
    'auto_suspend_after_failures' => (int) env('WEBHOOKS_AUTO_SUSPEND_AFTER', 25),

    /*
    |--------------------------------------------------------------------------
    | Throttling per endpoint (best-effort, soft)
    |--------------------------------------------------------------------------
    */
    'max_in_flight_per_endpoint' => (int) env('WEBHOOKS_MAX_INFLIGHT_PER_ENDPOINT', 8),

    /*
    |--------------------------------------------------------------------------
    | Whitelist d'events publiables
    |--------------------------------------------------------------------------
    | Tout event_code NON whitelisté est ignoré dans Dispatcher::emit().
    | Garde-fou anti-leak interne.
    */
    'allowed_events' => [
        // booking lifecycle
        'booking.created',
        'booking.scheduled',
        'booking.assigned',
        'booking.started',
        'booking.completed',
        'booking.cancelled',
        // payment
        'payment.succeeded',
        'payment.failed',
        'payment.refunded',
        // provider
        'provider.onboarded',
        'provider.kyc_approved',
        'provider.payouts_enabled',
        // user
        'user.created',
        'user.deleted',
        // contracts
        'contract.signed',
        // disputes
        'dispute.opened',
        'dispute.resolved',
        // quality
        'inspection.completed',
        // generic
        'test.ping',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP driver (real | fake)
    | "fake" stocke la requête au lieu de l'envoyer (tests).
    |--------------------------------------------------------------------------
    */
    'driver' => env('WEBHOOKS_DRIVER', 'real'),

    /*
    |--------------------------------------------------------------------------
    | Job queue
    |--------------------------------------------------------------------------
    */
    'queue' => env('WEBHOOKS_QUEUE', 'webhooks'),
    'queue_connection' => env('WEBHOOKS_QUEUE_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Persistance des réponses (tronquées)
    |--------------------------------------------------------------------------
    */
    'response_body_max_length' => (int) env('WEBHOOKS_RESPONSE_BODY_MAX', 4096),
];
