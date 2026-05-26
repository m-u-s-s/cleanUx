<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Premium Tier Definitions
    |--------------------------------------------------------------------------
    | All monetary amounts are in cents (Stripe convention).
    | Stripe price IDs must be created in the Stripe dashboard and set via env.
    */
    'tiers' => [
        'free' => [
            'name' => 'Gratuit',
            'price_monthly' => 0,
            'features' => [
                'max_bookings_month' => 5,
                'choose_provider' => false,
                'priority_dispatch' => false,
                'extended_history' => false,
                'insurance_included' => false,
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'price_monthly' => 999, // 9.99€/month in cents
            'stripe_price_monthly' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
            'stripe_price_yearly' => env('STRIPE_PRO_YEARLY_PRICE_ID'),
            'features' => [
                'max_bookings_month' => null, // unlimited
                'choose_provider' => true,
                'priority_dispatch' => true,
                'extended_history' => true,
                'insurance_included' => false,
            ],
        ],

        'business' => [
            'name' => 'Business',
            'price_monthly' => 2999, // 29.99€/month in cents
            'stripe_price_monthly' => env('STRIPE_BUSINESS_MONTHLY_PRICE_ID'),
            'stripe_price_yearly' => env('STRIPE_BUSINESS_YEARLY_PRICE_ID'),
            'features' => [
                'max_bookings_month' => null,
                'choose_provider' => true,
                'priority_dispatch' => true,
                'extended_history' => true,
                'insurance_included' => true,
                'multi_site' => true,
                'dedicated_account_manager' => true,
                'custom_invoicing' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tip Platform Commission
    |--------------------------------------------------------------------------
    | Percentage of each tip retained by the platform (0 = pass-through).
    | Applied in TipService::confirmCharge() when non-zero.
    */
    'tip_platform_fee_percent' => (int) env('TIP_PLATFORM_FEE_PERCENT', 0),
];
