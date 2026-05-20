<?php

return [
    'enabled' => env('SMS_ENABLED', true),

    'default_provider' => env('SMS_PROVIDER', 'mock'),

    'providers' => [
        'mock' => [
            'driver' => 'mock',
        ],
        'twilio' => [
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'webhook_token' => env('TWILIO_WEBHOOK_TOKEN'),
            'verify_signature' => (bool) env('TWILIO_VERIFY_SIGNATURE', true),
        ],
        'vonage' => [
            'driver' => 'vonage',
            'api_key' => env('VONAGE_API_KEY'),
            'api_secret' => env('VONAGE_API_SECRET'),
            'from' => env('VONAGE_FROM'),
            'signature_secret' => env('VONAGE_SIGNATURE_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting per phone (anti-spam, anti-toll fraud)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'per_phone_per_hour' => (int) env('SMS_MAX_PER_PHONE_PER_HOUR', 5),
        'per_phone_per_day' => (int) env('SMS_MAX_PER_PHONE_PER_DAY', 20),
        'per_user_per_hour' => (int) env('SMS_MAX_PER_USER_PER_HOUR', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP / Phone verification
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => (int) env('SMS_OTP_LENGTH', 6),
        'expires_minutes' => (int) env('SMS_OTP_EXPIRES_MINUTES', 10),
        'max_attempts' => (int) env('SMS_OTP_MAX_ATTEMPTS', 5),
        'cooldown_seconds' => (int) env('SMS_OTP_COOLDOWN_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    | Catégories métier pour traçabilité (impacts pricing, rate limits différenciés).
    */
    'categories' => [
        'transactional' => ['priority' => 'high'],
        'verification' => ['priority' => 'high'],
        'reminder' => ['priority' => 'medium'],
        'marketing' => ['priority' => 'low'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default sender
    |--------------------------------------------------------------------------
    */
    'default_sender' => env('SMS_DEFAULT_SENDER', 'CleanUx'),
];
