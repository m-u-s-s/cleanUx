<?php

return [
    'monitoring' => [
        'heartbeat_enabled' => filter_var(env('OPS_HEARTBEAT_ENABLED', true), FILTER_VALIDATE_BOOL),
        'heartbeat_disk' => env('OPS_HEARTBEAT_DISK', 'local'),
        'heartbeat_path' => env('OPS_HEARTBEAT_PATH', 'ops/heartbeat.json'),
        'heartbeat_cache_key' => env('OPS_HEARTBEAT_CACHE_KEY', 'cleanux:ops:heartbeat'),
        'heartbeat_max_age_seconds' => (int) env('OPS_HEARTBEAT_MAX_AGE_SECONDS', 900),
        'notify_email' => env('OPS_MONITORING_NOTIFY_EMAIL'),
        'failed_jobs_warning_threshold' => (int) env('OPS_FAILED_JOBS_WARNING_THRESHOLD', 1),
        'queue_backlog_warning_threshold' => (int) env('OPS_QUEUE_BACKLOG_WARNING_THRESHOLD', 50),
    ],

    'backups' => [
        'enabled' => filter_var(env('OPS_BACKUP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'disk' => env('OPS_BACKUP_DISK', 'local'),
        'path' => env('OPS_BACKUP_PATH', 'backups'),
        'retention_days' => (int) env('OPS_BACKUP_RETENTION_DAYS', 14),
    ],

    'deployment' => [
        'require_https_app_url' => filter_var(env('OPS_REQUIRE_HTTPS_APP_URL', true), FILTER_VALIDATE_BOOL),
        'expected_schedule_commands' => [
            'app:send-rendezvous-reminders',
            'app:prune-read-notifications --days=30',
            'google-calendar:sync --future-days=30',
            'finance:sync-documents',
            'finance:sync-documents --reminders',
            'app:ops-heartbeat',
            'app:production-health-check',
        ],
    ],
];
