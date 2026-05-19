<?php

return [
    'enabled' => env('QUALITY_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Photo storage
    |--------------------------------------------------------------------------
    */
    'photo_storage_disk' => env('QUALITY_PHOTO_DISK', 'public'),
    'photo_max_size_mb' => (int) env('QUALITY_PHOTO_MAX_SIZE_MB', 8),
    'photo_path_prefix' => env('QUALITY_PHOTO_PATH', 'quality/photos'),

    /*
    |--------------------------------------------------------------------------
    | Score thresholds (in percent of max score)
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'pass' => (float) env('QUALITY_PASS_THRESHOLD', 70.0),
        'excellent' => (float) env('QUALITY_EXCELLENT_THRESHOLD', 90.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signature
    |--------------------------------------------------------------------------
    */
    'signature_required_for_client_validation' => env('QUALITY_SIGNATURE_REQUIRED', true),
    'signature_terms_version' => env('QUALITY_SIGNATURE_TERMS_VERSION', '2026-05-v1'),

    /*
    |--------------------------------------------------------------------------
    | Phases supportées
    |--------------------------------------------------------------------------
    */
    'phases' => ['pre', 'during', 'post'],

    /*
    |--------------------------------------------------------------------------
    | Item types whitelistés
    |--------------------------------------------------------------------------
    */
    'item_types' => ['boolean', 'rating', 'text', 'photo', 'measurement', 'select'],
];
