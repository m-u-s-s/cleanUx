<?php

return [
    'enabled' => env('NOTIF_PREFS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Channels supportés
    |--------------------------------------------------------------------------
    */
    'channels' => ['email', 'sms', 'push', 'inapp', 'webhook'],

    /*
    |--------------------------------------------------------------------------
    | Catégories de notifications
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'transactional',  // booking confirmé, payment receipt, password reset
        'verification',   // OTP, email verify
        'reminder',       // rendez-vous demain 10h
        'marketing',      // promos, newsletter
        'support',        // tickets, disputes
        'security',       // login from new device, password change
        'product',        // feature releases, surveys
    ],

    /*
    |--------------------------------------------------------------------------
    | Default matrix : channel × category → is_allowed
    |--------------------------------------------------------------------------
    | Lacunes = false par défaut.
    | Marketing default = false partout (RGPD opt-in explicite obligatoire).
    */
    'default_matrix' => [
        'email' => [
            'transactional' => true,
            'verification' => true,
            'reminder' => true,
            'marketing' => false,
            'support' => true,
            'security' => true,
            'product' => false,
        ],
        'sms' => [
            'transactional' => true,
            'verification' => true,
            'reminder' => true,
            'marketing' => false,
            'support' => false,
            'security' => true,
            'product' => false,
        ],
        'push' => [
            'transactional' => true,
            'verification' => true,
            'reminder' => true,
            'marketing' => false,
            'support' => true,
            'security' => true,
            'product' => false,
        ],
        'inapp' => [
            'transactional' => true,
            'verification' => true,
            'reminder' => true,
            'marketing' => true,
            'support' => true,
            'security' => true,
            'product' => true,
        ],
        'webhook' => [
            'transactional' => true,
            'verification' => true,
            'reminder' => false,
            'marketing' => false,
            'support' => true,
            'security' => true,
            'product' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Forced-on : paires que l'utilisateur ne peut PAS désactiver
    |--------------------------------------------------------------------------
    | Légalement requis : verification + transactional sur email (minimum).
    | Le user peut désactiver channel SMS marketing mais pas email transactional.
    */
    'forced_on' => [
        ['channel' => 'email', 'category' => 'verification'],
        ['channel' => 'email', 'category' => 'transactional'],
        ['channel' => 'email', 'category' => 'security'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync vers autres modules (cross-modules propagation)
    |--------------------------------------------------------------------------
    */
    'sync_to_modules' => [
        'push' => env('NOTIF_PREFS_SYNC_PUSH', true),       // device_tokens.preferences[<category>]
        'marketing' => env('NOTIF_PREFS_SYNC_MARKETING', true),  // marketing_opt_outs row
    ],
];
