<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    | En prod : restreindre allowed_origins au domaine prod + capacitor schemes.
    | env('CORS_ALLOWED_ORIGINS') = liste CSV "https://app.cleanux.com,capacitor://localhost"
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS', env('APP_URL', 'http://localhost'))
    ))),

    'allowed_origins_patterns' => array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_PATTERNS', '')
    ))),

    'allowed_headers' => [
        'Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Requested-With',
        'X-CSRF-TOKEN', 'X-XSRF-TOKEN', 'X-Socket-Id', 'X-Tenant-Code',
    ],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 86400,

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),

    'finance' => [
        'default_employee_hourly_cost' => env('CLEANUX_EMPLOYEE_HOURLY_COST', 18),
        'default_travel_cost' => env('CLEANUX_TRAVEL_COST', 8),
        'default_material_cost_rate' => env('CLEANUX_MATERIAL_COST_RATE', 0.08),
    ],

];
