<?php

return [
    'platform_fee_percent' => (int) env('BRIO_PLATFORM_FEE_PERCENT', 15),

    // Décision produit 2026-06-11 : commission = TAUX UNIQUE au lancement.
    // L'infra de taux négocié par prestataire (ProviderProfile.commission_rate) reste
    // en base mais n'est appliquée que si ce flag est activé. Tant qu'il est off,
    // tout le monde est facturé à platform_fee_percent — et le calcul reste aligné
    // sur le montant réellement prélevé par Stripe (MissionPaymentService).
    'use_negotiated_commission' => (bool) env('BRIO_USE_NEGOTIATED_COMMISSION', false),

    'booking' => [
        'default_duration_minutes' => 90,
        'default_buffer_minutes' => 30,
    ],

    'notifications' => [
        'prune_after_days' => 30,
    ],

    'security' => [
        'require_active_account' => true,
    ],

    'seed' => [
        'profile' => env('BRIO_SEED_PROFILE'),
        'default_profile' => env('BRIO_SEED_DEFAULT_PROFILE', env('APP_ENV') === 'production' ? 'production' : 'demo'),
        'allowed_profiles' => ['demo', 'reference', 'production'],
    ],
];
