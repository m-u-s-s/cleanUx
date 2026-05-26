<?php

return [
    'platform_fee_percent' => (int) env('CLEANUX_PLATFORM_FEE_PERCENT', 15),

    'booking' => [
        'default_duration_minutes' => 90,
        'default_buffer_minutes' => 30,
    ],

    'notifications' => [
        'prune_after_days' => 30,
    ],

    'security' => [
        'require_active_account' => true,
    ],

    'seed' => [
        'profile' => env('CLEANUX_SEED_PROFILE'),
        'default_profile' => env('CLEANUX_SEED_DEFAULT_PROFILE', env('APP_ENV') === 'production' ? 'production' : 'demo'),
        'allowed_profiles' => ['demo', 'reference', 'production'],
    ],
];
