<?php

/**
 * Configuration du moteur de matching v2 (CleanUx).
 *
 * Les poids définissent l'importance relative de chaque dimension dans le score
 * final (0–100). La somme des poids doit faire 100 pour rester lisible.
 *
 * Tous les scores de dimension sont normalisés dans [0, 100] dans le
 * MatchingScoreEngine, puis multipliés par leur poids et sommés.
 */

return [
    'version' => env('MATCHING_VERSION', 'v2'),

    'enabled' => env('MATCHING_V2_ENABLED', true),

    'shadow_mode' => env('MATCHING_V2_SHADOW', false),

    'top_n' => (int) env('MATCHING_TOP_N', 5),

    'weights' => [
        'rating'           => (int) env('MATCHING_W_RATING', 25),
        'acceptance_rate'  => (int) env('MATCHING_W_ACCEPTANCE', 15),
        'completion_rate'  => (int) env('MATCHING_W_COMPLETION', 10),
        'response_time'    => (int) env('MATCHING_W_RESPONSE', 5),
        'zone_proximity'   => (int) env('MATCHING_W_ZONE', 15),
        'workload'         => (int) env('MATCHING_W_WORKLOAD', 10),
        'client_affinity'  => (int) env('MATCHING_W_AFFINITY', 10),
        'trade_specialty'  => (int) env('MATCHING_W_TRADE', 5),
        'recency_balance'  => (int) env('MATCHING_W_RECENCY', 5),
    ],

    'diversification' => [
        'enabled' => env('MATCHING_DIVERSIFICATION', true),
        'penalty_per_recent_mission' => (float) env('MATCHING_DIVERSIFICATION_PENALTY', 2.0),
        'recent_window_hours' => (int) env('MATCHING_RECENT_WINDOW_HOURS', 24),
    ],

    'thresholds' => [
        'min_acceptable_score' => (float) env('MATCHING_MIN_SCORE', 30),
        'fallback_if_no_match' => env('MATCHING_FALLBACK', true),
    ],

    'response_time' => [
        'excellent_seconds' => 60,
        'poor_seconds' => 600,
    ],
];
