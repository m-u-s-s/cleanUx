<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dispatch timeout (seconds)
    |--------------------------------------------------------------------------
    | How long a provider has to accept/decline a mission offer before the
    | system auto-escalates to the next candidate.
    |
    | Platform default applies when no trade-specific override is found.
    | Override per trade slug via `timeout_per_trade.<slug>`.
    */
    'default_timeout' => (int) env('DISPATCH_DEFAULT_TIMEOUT', 15),

    'timeout_per_trade' => [
        // Trades that need more time (complex job descriptions, tool checks…)
        'toiturier' => (int) env('DISPATCH_TIMEOUT_TOITURIER', 30),
        'electricite' => (int) env('DISPATCH_TIMEOUT_ELECTRICITE', 30),
        'plomberie' => (int) env('DISPATCH_TIMEOUT_PLOMBERIE', 30),
        'demenagement' => (int) env('DISPATCH_TIMEOUT_DEMENAGEMENT', 30),

        // Fast-response trades
        'nettoyage' => (int) env('DISPATCH_TIMEOUT_NETTOYAGE', 15),
        'babysitting' => (int) env('DISPATCH_TIMEOUT_BABYSITTING', 15),
        'jardinage' => (int) env('DISPATCH_TIMEOUT_JARDINAGE', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum escalation depth
    |--------------------------------------------------------------------------
    | After how many consecutive declines/timeouts should the system mark
    | the mission as "unassignable" and alert ops?
    */
    'max_escalation_depth' => (int) env('DISPATCH_MAX_ESCALATION', 5),

];
