<?php

return [
    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Mirror ActivityLogger → audit_events
    |--------------------------------------------------------------------------
    | Si activé, chaque appel à ActivityLogger::log() écrit aussi un row
    | audit_events (en plus de l'activity_logs legacy). À garder désactivé
    | par défaut pour ne pas exploser le volume ; activer modules-par-modules.
    */
    'mirror_activity_logger' => env('AUDIT_MIRROR_LEGACY', false),

    /*
    |--------------------------------------------------------------------------
    | PII redaction
    |--------------------------------------------------------------------------
    | drop_keys   : prop keys retirées entièrement de context
    | hash_keys   : prop keys hashées (sha256:<16chars>)
    | max_context_size_bytes : taille max du context JSON après redaction
    */
    'redaction' => [
        'drop_keys' => ['password', 'token', 'api_key', 'secret', 'cvv', 'webhook_secret', 'authorization'],
        'hash_keys' => ['email', 'phone', 'iban', 'card_number', 'ssn', 'ip_address', 'national_id'],
        'max_context_size_bytes' => (int) env('AUDIT_MAX_CONTEXT_BYTES', 32768),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention défauts par domaine (en jours)
    |--------------------------------------------------------------------------
    */
    'retention_days_by_domain' => [
        'auth'      => 365,   // 1 an (RGPD : logs de connexion conservés)
        'security'  => 730,   // 2 ans
        'finance'   => 2555,  // 7 ans (obligation légale comptable)
        'payment'   => 2555,
        'gdpr'      => 2190,  // 6 ans (preuves consentement)
        'kyc'       => 1825,  // 5 ans (KYC AML)
        'risk'      => 1095,  // 3 ans
        'audit'     => 1095,
        'booking'   => 730,
        'general'   => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Catégories de severity qui ne sont JAMAIS purgées même au-delà retention
    |--------------------------------------------------------------------------
    */
    'never_purge_severity' => ['critical'],

    /*
    |--------------------------------------------------------------------------
    | Limit safety nets pour le purge job
    |--------------------------------------------------------------------------
    */
    'purge_batch_size' => (int) env('AUDIT_PURGE_BATCH', 5000),
    'purge_max_runtime_seconds' => (int) env('AUDIT_PURGE_MAX_SECONDS', 300),
];
