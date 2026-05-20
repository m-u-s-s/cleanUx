<?php

return [
    'enabled' => env('TIPS_ENABLED', true),

    // Min/max en cents
    'min_amount_cents' => (int) env('TIPS_MIN_CENTS', 100),    // 1€
    'max_amount_cents' => (int) env('TIPS_MAX_CENTS', 50000),  // 500€

    // Suggestions par défaut (pourcent du total mission)
    'presets' => [
        ['label' => '10%', 'percent' => 10],
        ['label' => '15%', 'percent' => 15],
        ['label' => '20%', 'percent' => 20],
    ],

    // Statuts booking éligibles au tipping
    'eligible_booking_statuses' => ['termine', 'completed', 'closed'],

    // Bonus loyalty points par euro tippé (incite à tipper)
    'client_bonus_points_per_euro' => (float) env('TIPS_BONUS_POINTS_PER_EUR', 1.0),

    // Fenêtre pendant laquelle le client peut tipper après completion (en jours)
    'window_days' => (int) env('TIPS_WINDOW_DAYS', 7),
];
