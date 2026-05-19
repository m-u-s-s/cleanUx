<?php

return [
    'enabled' => env('AVAILABILITY_ENABLED', true),

    'default_timezone' => env('AVAILABILITY_DEFAULT_TZ', 'Europe/Brussels'),

    /*
    | Durée par défaut d'un soft-hold pendant le booking flow.
    | Au-delà, le hold est libéré automatiquement.
    */
    'hold_duration_minutes' => (int) env('AVAILABILITY_HOLD_MINUTES', 10),

    /*
    | Limite de calcul de fenêtres : on ne projette jamais plus loin
    | que `max_lookahead_days` dans le futur, pour borner la complexité.
    */
    'max_lookahead_days' => (int) env('AVAILABILITY_MAX_LOOKAHEAD', 90),

    /*
    | Minutes minimales entre deux missions (buffer trajet, prep).
    */
    'transition_buffer_minutes' => (int) env('AVAILABILITY_TRANSITION_BUFFER', 15),

    /*
    | Intégrations calendrier externes (skeletons, pas câblés runtime).
    */
    'integrations' => [
        'ical_export' => [
            'enabled' => env('AVAILABILITY_ICAL_EXPORT', true),
        ],
        'google_calendar' => [
            'enabled' => env('AVAILABILITY_GOOGLE_ENABLED', false),
            'client_id' => env('GOOGLE_CAL_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CAL_CLIENT_SECRET'),
        ],
    ],
];
