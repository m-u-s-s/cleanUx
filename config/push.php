<?php

return [
    'enabled' => env('PUSH_ENABLED', true),

    'default_provider' => env('PUSH_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'driver' => 'mock',
        ],

        'fcm' => [
            'driver' => 'fcm',
            'credentials_path' => env('FCM_CREDENTIALS_PATH'),
            'project_id' => env('FCM_PROJECT_ID'),
            'http_timeout' => (int) env('FCM_HTTP_TIMEOUT', 10),
        ],

        'apns' => [
            'driver' => 'apns',
            'key_path' => env('APNS_KEY_PATH'),
            'key_id' => env('APNS_KEY_ID'),
            'team_id' => env('APNS_TEAM_ID'),
            'bundle_id' => env('APNS_BUNDLE_ID'),
            'environment' => env('APNS_ENVIRONMENT', 'production'),
        ],
    ],

    'rate_limits' => [
        'per_token_per_minute' => (int) env('PUSH_MAX_PER_TOKEN_PER_MINUTE', 10),
        'per_user_per_minute' => (int) env('PUSH_MAX_PER_USER_PER_MINUTE', 30),
    ],

    'categories' => [
        'transactional' => ['priority' => 'high', 'default_opt_in' => true],
        'verification' => ['priority' => 'high', 'default_opt_in' => true],
        'reminder' => ['priority' => 'medium', 'default_opt_in' => true],
        'marketing' => ['priority' => 'low', 'default_opt_in' => false],
    ],

    /*
    | Token cleanup: invalidate tokens unused longer than this
    */
    'token_stale_after_days' => (int) env('PUSH_TOKEN_STALE_AFTER_DAYS', 90),
];
