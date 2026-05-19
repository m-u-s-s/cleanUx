<?php

return [
    'enabled' => env('MARKETING_ENABLED', true),

    'default_locale' => env('MARKETING_DEFAULT_LOCALE', 'fr'),

    /*
    |--------------------------------------------------------------------------
    | Recompute segments cadence (hours)
    |--------------------------------------------------------------------------
    */
    'segment_recompute_interval_hours' => (int) env('MARKETING_SEGMENT_RECOMPUTE_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Channel throttling (sends per minute, global per channel)
    |--------------------------------------------------------------------------
    */
    'throttling' => [
        'email_per_minute' => (int) env('MARKETING_EMAIL_PER_MIN', 120),
        'sms_per_minute'   => (int) env('MARKETING_SMS_PER_MIN', 60),
        'push_per_minute'  => (int) env('MARKETING_PUSH_PER_MIN', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | A/B testing
    |--------------------------------------------------------------------------
    | Si activé, les steps avec `variant_label` non-null sont distribués
    | déterministiquement par sha1(user_id + campaign_code) % len(variants).
    */
    'ab_test_enabled' => env('MARKETING_AB_TEST_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Marketing categories qui peuvent être opt-out (séparé de transactional)
    |--------------------------------------------------------------------------
    */
    'optoutable_channels' => ['email', 'sms', 'push'],

    /*
    |--------------------------------------------------------------------------
    | Whitelist DSL operators acceptés dans segments.rules
    |--------------------------------------------------------------------------
    | Toute clé non-listée est rejetée silencieusement.
    */
    'segment_operators' => [
        'eq', 'neq', 'in', 'not_in',
        'gt', 'gte', 'lt', 'lte',
        'older_than_days', 'newer_than_days',
        'is_null', 'is_not_null',
        'contains', 'starts_with', 'ends_with',
    ],

    'segment_fields' => [
        'role', 'locale', 'country_code', 'email_domain',
        'created_at', 'last_login_at',
        'bookings_count', 'last_booking_at',
        'total_spent_cents',
    ],
];
