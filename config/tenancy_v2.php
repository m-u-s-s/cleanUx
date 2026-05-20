<?php

return [
    'enabled' => env('TENANCY_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Resolution strategy
    |--------------------------------------------------------------------------
    | subdomain : extrait premier segment (acme.cleanux.com → "acme")
    | header   : utilise header X-Tenant-Code
    | path     : utilise premier segment de path (/acme/admin/...)
    |
    | Plusieurs strategies peuvent être combinées (premier match gagne).
    */
    'resolution_strategies' => ['header', 'subdomain'],

    /*
    |--------------------------------------------------------------------------
    | Default fallback tenant code (single-tenant mode / dev)
    |--------------------------------------------------------------------------
    | Si aucune strategy ne résout, utilise ce tenant.
    */
    'default_tenant_code' => env('TENANCY_DEFAULT_TENANT', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Subdomain extraction
    |--------------------------------------------------------------------------
    | Le pattern doit capturer le subdomain dans le 1er groupe.
    | Default : extrait le 1er segment avant le 1er point pour hosts à 3+ segments.
    */
    'subdomain_pattern' => '/^([a-z0-9-]+)\.(.+\..+)$/i',
    'reserved_subdomains' => ['www', 'api', 'admin', 'app', 'auth', 'static', 'cdn'],

    /*
    |--------------------------------------------------------------------------
    | Header config
    |--------------------------------------------------------------------------
    */
    'header_name' => env('TENANCY_HEADER', 'X-Tenant-Code'),

    /*
    |--------------------------------------------------------------------------
    | Theming defaults (fallback si tenant n'override pas)
    |--------------------------------------------------------------------------
    */
    'theming_defaults' => [
        'logo_url' => '/images/logo-cleanux.svg',
        'favicon_url' => '/favicon.ico',
        'primary_color' => '#4F46E5',   // indigo-600
        'secondary_color' => '#0EA5E9', // sky-500
        'accent_color' => '#F59E0B',    // amber-500
        'font_family' => 'Inter, sans-serif',
        'app_name' => 'CleanUx',
        'support_email' => 'support@cleanux.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans + limites
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'basic' => [
            'name' => 'Basic',
            'max_users' => 25,
            'max_providers' => 10,
            'max_bookings_per_month' => 500,
            'custom_domain' => false,
            'custom_theming' => false,
        ],
        'growth' => [
            'name' => 'Growth',
            'max_users' => 250,
            'max_providers' => 100,
            'max_bookings_per_month' => 10_000,
            'custom_domain' => true,
            'custom_theming' => true,
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'max_users' => null,
            'max_providers' => null,
            'max_bookings_per_month' => null,
            'custom_domain' => true,
            'custom_theming' => true,
        ],
    ],
    'allowed_plans' => ['basic', 'growth', 'enterprise'],
    'default_plan' => 'basic',

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    */
    'trial_days_default' => (int) env('TENANCY_TRIAL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Roles utilisateurs au sein d'un tenant
    |--------------------------------------------------------------------------
    */
    'tenant_user_roles' => ['owner', 'admin', 'member', 'guest'],
];
