<?php

return [
    'enabled' => env('SUBSCRIPTIONS_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Billing driver
    |--------------------------------------------------------------------------
    | mock   : pas d'appel Stripe — utile en CI/dev/staging
    | stripe : appel Stripe::PaymentIntent réel
    */
    'billing_driver' => env('SUBSCRIPTIONS_BILLING_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Billing periods (jours par défaut)
    |--------------------------------------------------------------------------
    */
    'periods' => [
        'weekly' => 7,
        'biweekly' => 14,
        'monthly' => 30,
        'quarterly' => 91,
        'semiannual' => 182,
        'yearly' => 365,
    ],
    'allowed_periods' => ['weekly', 'biweekly', 'monthly', 'quarterly', 'semiannual', 'yearly'],

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    */
    'trial_days_default' => (int) env('SUBSCRIPTIONS_TRIAL_DAYS', 0),
    'allow_trial_extension' => env('SUBSCRIPTIONS_ALLOW_TRIAL_EXTENSION', false),

    /*
    |--------------------------------------------------------------------------
    | Past-due handling
    |--------------------------------------------------------------------------
    | Grace period entre première facture failed et passage à status=past_due.
    | Après auto_cancel_after_failed_days, la subscription est auto-cancelled.
    */
    'grace_days_past_due' => (int) env('SUBSCRIPTIONS_GRACE_DAYS', 3),
    'auto_cancel_after_failed_days' => (int) env('SUBSCRIPTIONS_AUTO_CANCEL_DAYS', 15),
    'max_consecutive_failed_charges' => (int) env('SUBSCRIPTIONS_MAX_FAILED', 4),

    /*
    |--------------------------------------------------------------------------
    | Status enum (sync avec Subscription::STATUS_*)
    |--------------------------------------------------------------------------
    */
    'statuses' => [
        'trialing', 'active', 'paused', 'past_due', 'cancelled', 'expired',
    ],

    /*
    |--------------------------------------------------------------------------
    | Devises supportées
    |--------------------------------------------------------------------------
    */
    'allowed_currencies' => ['EUR', 'USD', 'GBP', 'CHF'],
    'default_currency' => env('SUBSCRIPTIONS_DEFAULT_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Pause limits (anti-abuse)
    |--------------------------------------------------------------------------
    */
    'max_paused_days_per_year' => (int) env('SUBSCRIPTIONS_MAX_PAUSE_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Stripe specifics (utilisé par StripeBillingProvider quand driver=stripe)
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'capture_method' => env('SUBSCRIPTIONS_STRIPE_CAPTURE_METHOD', 'automatic'),
        'statement_descriptor' => env('SUBSCRIPTIONS_STRIPE_DESCRIPTOR', 'CleanUx Sub'),
    ],
];
