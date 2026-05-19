<?php

return [
    'enabled' => env('PRICING_V2_ENABLED', true),

    'default_currency' => env('PRICING_DEFAULT_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Variables acceptées dans `variables_snapshot` et `applies_when` DSL
    |--------------------------------------------------------------------------
    | Anything outside this list is silently dropped by the engine.
    */
    'variable_keys' => [
        'surface_m2', 'frequency', 'urgency', 'zone_code', 'hours',
        'locale', 'country_code', 'is_recurrent', 'rooms_count',
        'floor_count', 'has_pets', 'parking_available',
        'day_of_week', 'hour_of_day', 'distance_km',
        'service_options',
    ],

    /*
    |--------------------------------------------------------------------------
    | Opérateurs DSL whitelistés
    |--------------------------------------------------------------------------
    */
    'condition_operators' => [
        'eq', 'neq', 'in', 'not_in',
        'gt', 'gte', 'lt', 'lte', 'between',
        'is_true', 'is_false', 'is_null', 'is_not_null',
        'contains',
    ],

    /*
    |--------------------------------------------------------------------------
    | Adjustments whitelistés (kind enum)
    |--------------------------------------------------------------------------
    | add_flat_cents : +N cents
    | add_percent    : ×(1 + N/100)
    | multiply       : ×N (factor)
    | per_unit_cents : +N × variables.<unit_key>  (params : unit_key)
    | set_minimum    : clamp to >= N cents
    | set_maximum    : clamp to <= N cents
    | replace_base   : set base price to N cents (rare ; "from scratch" reset)
    */
    'adjustment_kinds' => [
        'add_flat_cents', 'add_percent', 'multiply',
        'per_unit_cents', 'set_minimum', 'set_maximum', 'replace_base',
    ],

    'ab_test_enabled' => env('PRICING_AB_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting endpoint quote public
    |--------------------------------------------------------------------------
    */
    'quote_rate_limit_per_minute' => (int) env('PRICING_QUOTE_RATE_LIMIT', 60),
];
