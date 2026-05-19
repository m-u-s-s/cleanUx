<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Grace periods (jours)
    |--------------------------------------------------------------------------
    | Délai avant exécution effective d'une demande sensible (erasure).
    | Permet à l'utilisateur de changer d'avis OU à l'admin de bloquer.
    */
    'erasure_grace_period_days' => (int) env('GDPR_ERASURE_GRACE_DAYS', 30),
    'export_expiry_days' => (int) env('GDPR_EXPORT_EXPIRY_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Anonymisation
    |--------------------------------------------------------------------------
    | Quand un user est "erasé", on ne supprime PAS son row (préserve les FKs
    | comptables/audit) — on anonymise les champs PII.
    */
    'anonymized_email_template' => env('GDPR_ANONYMIZED_EMAIL', 'deleted_{id}@anonymized.cleanux'),
    'anonymized_name' => env('GDPR_ANONYMIZED_NAME', 'Utilisateur supprimé'),
    'anonymized_phone' => null,

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'export_disk' => env('GDPR_EXPORT_DISK', 'local'),
    'export_path' => env('GDPR_EXPORT_PATH', 'gdpr-exports'),

    /*
    |--------------------------------------------------------------------------
    | Retention policies
    |--------------------------------------------------------------------------
    | Combien de temps on conserve chaque type de données.
    | Les compliances varient par pays — défaults conservatifs.
    */
    'retention' => [
        'activity_logs_days' => (int) env('GDPR_RETENTION_ACTIVITY_DAYS', 730), // 2 ans
        'notifications_days' => (int) env('GDPR_RETENTION_NOTIFICATIONS_DAYS', 365),
        'sessions_days' => (int) env('GDPR_RETENTION_SESSIONS_DAYS', 90),
        'failed_jobs_days' => (int) env('GDPR_RETENTION_FAILED_JOBS_DAYS', 90),
        // Comptable (bookings, invoices, payments) : 10 ans en Belgique/France
        'financial_records_days' => (int) env('GDPR_RETENTION_FINANCIAL_DAYS', 3650),
    ],

    'reference_prefix' => env('GDPR_REF_PREFIX', 'GDPR'),
];
