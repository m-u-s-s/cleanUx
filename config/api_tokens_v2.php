<?php

return [
    'enabled' => env('API_TOKENS_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default rate limit (requests per minute) appliqué quand
    | personal_access_tokens.rate_limit_per_minute IS NULL.
    |--------------------------------------------------------------------------
    */
    'default_rate_limit_per_minute' => (int) env('API_TOKEN_RATE_LIMIT_PM', 120),
    'admin_rate_limit_per_minute' => (int) env('API_TOKEN_ADMIN_RATE_LIMIT_PM', 600),

    /*
    |--------------------------------------------------------------------------
    | Expiration par défaut (jours). 0 = jamais.
    |--------------------------------------------------------------------------
    */
    'default_expiry_days' => (int) env('API_TOKEN_DEFAULT_EXPIRY_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Rotation
    |--------------------------------------------------------------------------
    | Grace period en heures pendant lequel l'ancien token continue à fonctionner
    | après rotation. Permet à l'intégrateur B2B de migrer son env sans coupure.
    */
    'rotation_grace_hours' => (int) env('API_TOKEN_ROTATION_GRACE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Audit usage logging
    |--------------------------------------------------------------------------
    */
    'audit_enabled' => env('API_TOKEN_AUDIT_ENABLED', true),
    'audit_sample_rate' => (float) env('API_TOKEN_AUDIT_SAMPLE_RATE', 1.0),
    'audit_retention_days' => (int) env('API_TOKEN_AUDIT_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Owner roles
    |--------------------------------------------------------------------------
    */
    'owner_roles' => ['api_partner', 'admin', 'client', 'provider', 'enterprise'],
    'default_owner_role' => 'api_partner',

    /*
    |--------------------------------------------------------------------------
    | Whitelist de scopes catalog (utilisé pour valider scope au POST create)
    | Sync avec api_token_scopes en DB via seeder ApiTokenScopesSeeder.
    |--------------------------------------------------------------------------
    */
    'allowed_scopes' => [
        'read:bookings', 'write:bookings',
        'read:providers', 'write:providers',
        'read:clients',
        'read:payments', 'write:payments',
        'read:contracts',
        'read:analytics',
        'read:availability', 'write:availability',
        'read:invoices',
        'read:disputes', 'write:disputes',
        'read:quality',
        'admin:webhooks',
        'admin:users',
        'admin:everything',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scopes "dangereux" qui nécessitent confirmation explicite côté UI admin
    | et qui sont audités systématiquement (override sample_rate).
    |--------------------------------------------------------------------------
    */
    'dangerous_scopes' => [
        'write:payments', 'write:providers', 'admin:users', 'admin:everything',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes exemptes du middleware d'audit (e.g. health-check)
    |--------------------------------------------------------------------------
    */
    'audit_excluded_paths' => [
        'api/health', 'api/ping', 'api/v2/tokens/scopes',
    ],
];
