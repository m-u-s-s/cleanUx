<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Referral Program Toggle
    |--------------------------------------------------------------------------
    */
    'enabled' => env('REFERRAL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Reward Amounts
    |--------------------------------------------------------------------------
    | All amounts in cents (Stripe convention).
    | These override the constants in ReferralService when set to non-zero.
    |
    | referrer: reward granted to the user who shared their code, after
    |           the referee completes their first booking.
    | referee:  welcome discount granted to the new user on first booking.
    */
    'rewards' => [
        'referrer' => [
            'type' => 'credit',   // credit | discount | loyalty_points
            'amount' => 1000,     // 10.00€
            'trigger' => 'referee_first_booking_completed',
        ],
        'referee' => [
            'type' => 'discount',
            'amount' => 1500,     // 15.00€
            'applies_to' => 'first_booking',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_referrals_per_user' => (int) env('REFERRAL_MAX_PER_USER', 50),
        'referral_code_expiry_days' => (int) env('REFERRAL_CODE_EXPIRY_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Viral Tier Thresholds
    |--------------------------------------------------------------------------
    | Progressive bonus tiers consumed by ReferralViralTierEngine.
    | Overrides the hardcoded defaultTiers() array in that class when
    | config key 'promotions.referral_tiers' is absent.
    */
    'viral_tiers' => [
        [
            'code' => 'starter',
            'name' => 'Premier ami',
            'threshold' => 1,
            'loyalty_points' => 100,
            'credit_cents' => 1000,
            'reward_description' => '10€ + 100 pts loyalty',
        ],
        [
            'code' => 'enthusiast',
            'name' => 'Enthousiaste',
            'threshold' => 3,
            'loyalty_points' => 500,
            'credit_cents' => 5000,
            'reward_description' => '50€ bonus + 500 pts',
        ],
        [
            'code' => 'champion',
            'name' => 'Champion',
            'threshold' => 10,
            'loyalty_points' => 2000,
            'credit_cents' => 20000,
            'reward_description' => '200€ + badge VIP + abo 1 mois gratuit',
        ],
        [
            'code' => 'ambassador',
            'name' => 'Ambassadeur',
            'threshold' => 25,
            'loyalty_points' => 5000,
            'credit_cents' => 50000,
            'reward_description' => '500€ + statut Ambassador + early access features',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Sharing Message Templates
    |--------------------------------------------------------------------------
    | Placeholders: {code} = referral code, {link} = referral landing URL.
    */
    'sharing' => [
        'message_template_fr' => "Découvrez CleanUx ! Utilisez mon code {code} et obtenez 15€ de réduction sur votre premier service. {link}",
        'message_template_nl' => "Ontdek CleanUx! Gebruik mijn code {code} en krijg 15€ korting op je eerste dienst. {link}",
        'message_template_en' => "Discover CleanUx! Use my code {code} and get €15 off your first service. {link}",
    ],

    /*
    |--------------------------------------------------------------------------
    | Landing URL template
    |--------------------------------------------------------------------------
    | {code} is replaced with the referrer's referral_code at runtime.
    */
    'landing_url_template' => env('REFERRAL_LANDING_URL', '/register?ref={code}'),
];
