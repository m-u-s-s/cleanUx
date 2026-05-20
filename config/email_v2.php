<?php

return [
    'enabled' => env('EMAIL_V2_ENABLED', true),

    'provider' => env('EMAIL_V2_PROVIDER', 'mock'),
    // mock | mailgun | ses | sendgrid | smtp

    'providers' => [
        'mailgun' => [
            'domain' => env('MAILGUN_DOMAIN'),
            'secret' => env('MAILGUN_SECRET'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        ],
        'ses' => [
            'region' => env('AWS_REGION', 'eu-west-1'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
        'sendgrid' => [
            'api_key' => env('SENDGRID_API_KEY'),
            'endpoint' => 'https://api.sendgrid.com/v3/mail/send',
        ],
        'smtp' => [
            'host' => env('MAIL_HOST'),
            'port' => env('MAIL_PORT'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'encryption' => env('MAIL_ENCRYPTION'),
        ],
    ],

    'from_default' => [
        'email' => env('MAIL_FROM_ADDRESS', 'noreply@cleanux.com'),
        'name' => env('MAIL_FROM_NAME', 'CleanUx'),
    ],

    'reply_to_default' => env('EMAIL_V2_REPLY_TO', 'support@cleanux.com'),

    'allowed_categories' => ['transactional', 'marketing', 'notification', 'system'],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting per recipient (anti-spam)
    |--------------------------------------------------------------------------
    */
    'rate_limit_per_recipient_per_hour' => (int) env('EMAIL_V2_RATE_LIMIT_HOUR', 20),
    'rate_limit_per_recipient_per_day' => (int) env('EMAIL_V2_RATE_LIMIT_DAY', 100),

    /*
    |--------------------------------------------------------------------------
    | Opt-out check (intégration NotificationPreferences)
    |--------------------------------------------------------------------------
    | Si true, vérifie marketing_opt_outs + NotificationPreference avant
    | d'envoyer un email de catégorie marketing.
    */
    'check_opt_outs' => env('EMAIL_V2_CHECK_OPT_OUTS', true),

    /*
    |--------------------------------------------------------------------------
    | Retention (jours) avant purge des emails envoyés
    |--------------------------------------------------------------------------
    */
    'retention_days' => (int) env('EMAIL_V2_RETENTION_DAYS', 90),
    'retention_days_marketing' => (int) env('EMAIL_V2_RETENTION_MARKETING_DAYS', 30),
];
