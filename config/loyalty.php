<?php

/**
 * Configuration du programme de fidélité CleanUx.
 *
 * Les tiers sont seedés en DB via LoyaltyTierSeeder, mais cette config sert
 * de source de vérité pour les EARNING RULES et défauts.
 */

return [
    'enabled' => env('LOYALTY_ENABLED', true),

    'currency' => 'points',

    /*
    |--------------------------------------------------------------------------
    | Période de calcul du tier
    |--------------------------------------------------------------------------
    | Fenêtre roulante (en jours) pour calculer le tier courant.
    | 365 = 12 mois — pratique standard (Sephora, Marriott).
    */
    'tier_period_days' => (int) env('LOYALTY_TIER_PERIOD_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Earning rules
    |--------------------------------------------------------------------------
    | Combien de points par action.
    */
    'earning' => [
        // 10 points par € dépensé (100 points pour 10€)
        'points_per_euro_spent' => (float) env('LOYALTY_PTS_PER_EURO', 10),

        // Bonus à la 1ère mission complétée
        'first_booking_bonus' => (int) env('LOYALTY_FIRST_BOOKING_BONUS', 500),

        // Bonus signup (création du compte)
        'signup_bonus' => (int) env('LOYALTY_SIGNUP_BONUS', 100),

        // Parrain quand son filleul complète sa 1ère mission
        'referral_qualified_bonus' => (int) env('LOYALTY_REFERRAL_BONUS', 1000),

        // Points par avis donné (avec note)
        'rating_given_bonus' => (int) env('LOYALTY_RATING_BONUS', 50),

        // Bonus anniversaire (1x/an)
        'anniversary_bonus' => (int) env('LOYALTY_ANNIVERSARY_BONUS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiers (seedés en DB par LoyaltyTierSeeder, ré-importables)
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        [
            'slug' => 'bronze',
            'name' => 'Bronze',
            'min_period_points' => 0,
            'rank' => 1,
            'color' => '#A97142',
            'icon' => '🥉',
            'discount_percent' => 0,
            'priority_dispatch' => false,
            'vip_support' => false,
            'benefits' => ['Accès standard à toutes les fonctionnalités'],
        ],
        [
            'slug' => 'silver',
            'name' => 'Silver',
            'min_period_points' => 1000,
            'rank' => 2,
            'color' => '#C0C0C0',
            'icon' => '🥈',
            'discount_percent' => 5.0,
            'priority_dispatch' => false,
            'vip_support' => false,
            'benefits' => ['-5% sur toutes les réservations', 'Bonus points x1.2'],
        ],
        [
            'slug' => 'gold',
            'name' => 'Gold',
            'min_period_points' => 5000,
            'rank' => 3,
            'color' => '#FFD700',
            'icon' => '🥇',
            'discount_percent' => 10.0,
            'priority_dispatch' => true,
            'vip_support' => false,
            'benefits' => ['-10% sur toutes les réservations', 'Dispatch prioritaire', 'Bonus points x1.5'],
        ],
        [
            'slug' => 'platinum',
            'name' => 'Platinum',
            'min_period_points' => 15000,
            'rank' => 4,
            'color' => '#E5E4E2',
            'icon' => '💎',
            'discount_percent' => 15.0,
            'priority_dispatch' => true,
            'vip_support' => true,
            'benefits' => ['-15% sur toutes les réservations', 'Dispatch prioritaire', 'Support VIP 24/7', 'Bonus points x2'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Points expiry (optionnel)
    |--------------------------------------------------------------------------
    | Si défini, les points expirent après X jours d'inactivité.
    | Set à null pour désactiver l'expiration.
    */
    'points_expiry_days' => env('LOYALTY_POINTS_EXPIRY_DAYS', null),

    'tier_multipliers' => [
        'bronze' => 1.0,
        'silver' => 1.2,
        'gold' => 1.5,
        'platinum' => 2.0,
    ],
];
