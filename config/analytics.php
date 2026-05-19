<?php

return [
    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    */
    'session' => [
        'cookie_name' => env('ANALYTICS_SESSION_COOKIE', 'cleanux_aid'),
        'inactivity_minutes' => (int) env('ANALYTICS_SESSION_INACTIVITY_MIN', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event whitelist (server-trusted events). Client-sent events outside this
    | list are silently dropped to prevent log pollution / abuse.
    |--------------------------------------------------------------------------
    */
    'allowed_events' => [
        // Lifecycle (auto-tracked)
        'user.registered',
        'user.logged_in',
        'booking.created',
        'booking.confirmed',
        'booking.cancelled',
        'booking.completed',
        'rating.published',
        'promo.redeemed',
        'loyalty.points_awarded',
        'dispute.opened',
        'kyc.completed',

        // Client funnel (web/mobile pushed via API)
        'page.viewed',
        'search.performed',
        'provider.viewed',
        'booking.started',
        'booking.payment_started',
        'checkout.completed',

        // Engagement
        'cta.clicked',
        'feature.used',
        'notification.clicked',
        'error.client',
    ],

    /*
    |--------------------------------------------------------------------------
    | Property sanitization
    |--------------------------------------------------------------------------
    | hash_keys: prop keys whose value is replaced by sha256 (PII)
    | drop_keys: prop keys removed entirely
    | max_string_length: clamp string values
    */
    'sanitize' => [
        'hash_keys' => ['email', 'phone', 'card_number', 'ip_address'],
        'drop_keys' => ['password', 'token', 'api_key', 'secret', 'cvv'],
        'max_string_length' => 2000,
        'max_properties' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (per ip / per user / per session)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'per_ip_per_minute' => (int) env('ANALYTICS_MAX_PER_IP_PER_MIN', 240),
        'per_user_per_minute' => (int) env('ANALYTICS_MAX_PER_USER_PER_MIN', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | External forwarders (placeholders)
    |--------------------------------------------------------------------------
    */
    'forwarders' => [
        'google_analytics' => [
            'enabled' => env('GA_ENABLED', false),
            'measurement_id' => env('GA_MEASUREMENT_ID'),
            'api_secret' => env('GA_API_SECRET'),
        ],
        'mixpanel' => [
            'enabled' => env('MIXPANEL_ENABLED', false),
            'project_token' => env('MIXPANEL_PROJECT_TOKEN'),
        ],
    ],
];
