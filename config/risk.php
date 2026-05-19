<?php

return [
    'enabled' => env('RISK_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Décisions auto par score
    |--------------------------------------------------------------------------
    | score < review_threshold  → decision = allow
    | review_threshold <= score < block_threshold → decision = review (hold)
    | score >= block_threshold → decision = block (hold + flag)
    */
    'thresholds' => [
        'review' => (int) env('RISK_REVIEW_THRESHOLD', 50),
        'block' => (int) env('RISK_BLOCK_THRESHOLD', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hold lifecycle
    |--------------------------------------------------------------------------
    */
    'hold_duration_minutes' => (int) env('RISK_HOLD_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Rules registry — codes → FQCN. Toutes les règles enregistrées ici sont
    | candidates ; leur activation effective dépend de la table `risk_rules`
    | (is_active=true).
    |--------------------------------------------------------------------------
    */
    'rules' => [
        \App\Services\Risk\Rules\BookingVelocityRule::class,
        \App\Services\Risk\Rules\PaymentDeclineRule::class,
        \App\Services\Risk\Rules\IpReputationRule::class,
        \App\Services\Risk\Rules\AccountAgeRule::class,
        \App\Services\Risk\Rules\GeoMismatchRule::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Liste de CIDRs / IPs flaggés (proxy, datacenter, TOR, etc.). Peut être
    | enrichie via .env (comma separated) ou via la table risk_rules.params.
    |--------------------------------------------------------------------------
    */
    'flagged_networks' => array_filter(explode(',', (string) env('RISK_FLAGGED_NETWORKS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Roles qui contournent le risk scoring (jamais bloqués).
    |--------------------------------------------------------------------------
    */
    'bypass_roles' => ['admin', 'super-admin'],
];
