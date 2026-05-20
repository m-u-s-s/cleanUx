<?php

/**
 * Sentry config — actif quand sentry/sentry-laravel est installé.
 * Si le package n'est pas présent, ce fichier est ignoré silencieusement.
 *
 * Pour activer en prod :
 *   composer require sentry/sentry-laravel
 *   php artisan sentry:publish --dsn="https://..."
 *   Remplir SENTRY_LARAVEL_DSN dans .env
 */

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('SENTRY_RELEASE', null),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // RGPD : ne pas envoyer email/IP user par défaut
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => false,   // RGPD : pas de bindings (potentielles PII)
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
    ],

    /**
     * Filtre `before_send` : ignore les soft-fail breadcrumbs attendus.
     */
    'before_send' => function ($event) {
        if (! is_object($event) || ! method_exists($event, 'getMessage')) {
            return $event;
        }
        $msg = (string) $event->getMessage();
        $ignoredPrefixes = [
            '[business_webhook]',
            '[chat_auto]',
            '[critical_audit]',
            '[accounting_auto_post]',
            '[fleet_v2]',
            '[geo_v2]',
            '[trip_tracking]',
            '[loyalty_redemption]',
            '[tips]',
            '[presence_auto]',
        ];
        foreach ($ignoredPrefixes as $prefix) {
            if (str_starts_with($msg, $prefix)) {
                return null;
            }
        }
        return $event;
    },
];
